"""Reproduce measurements and write all evidence to an isolated directory."""
import argparse
from collections import Counter
import csv
import json
import mimetypes
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
from urllib.parse import urlsplit

import fitz
from fontTools.ttLib import TTFont
from PIL import Image
from playwright.sync_api import sync_playwright

from fixtures import build

HERE = Path(__file__).resolve().parent
PT_MM = 25.4 / 72
COLOR = (18/255, 60/255, 162/255)
FILES = {'Regular':'DejaVuSans.ttf', 'Bold':'DejaVuSans-Bold.ttf',
         'Italic':'DejaVuSans-Oblique.ttf', 'BoldItalic':'DejaVuSans-BoldOblique.ttf'}


def close(a, b):
    return a is not None and len(a) == len(b) and max(abs(x-y) for x,y in zip(a,b)) <= .008


def grouped(items, key, threshold=.7):
    groups = []
    for item in sorted(items, key=key):
        if not groups or abs(key(item) - key(groups[-1][0])) > threshold:
            groups.append([])
        groups[-1].append(item)
    return groups


def pdf_data(path, config):
    with fitz.open(path) as doc:
        page = doc[0]
        traces = [t for t in page.get_texttrace() if close(t['color'], COLOR)]
        glyphs = []
        for span in traces:
            for value, glyph, origin, bounds in span['chars']:
                if value < 0 or not chr(value).strip():
                    continue
                glyphs.append(dict(char=chr(value), glyph=glyph, font=span['font'], pt=span['size'],
                    x=origin[0]*PT_MM, y=origin[1]*PT_MM, bounds=[p*PT_MM for p in bounds]))
        # Diacritics can have positioned origins. A separate origin is not a line.
        lines = grouped(glyphs, lambda glyph: glyph['y'], threshold=2.1)
        dividers = [config['boxes'][0]['y']-3, config['boxes'][1]['y']-3]
        metrics = {}
        for box in config['boxes']:
            def within(glyph):
                if box['id'] == 'content':
                    return dividers[0] <= glyph['y'] < dividers[1]
                return glyph['y'] >= dividers[1] and (glyph['x'] < config['page'][0]/2) == (box['id'] == 'date')
            rows = [[g for g in row if within(g)] for row in lines if any(within(g) for g in row)]
            fit = bool(rows) and all(
                g['bounds'][0] >= box['x']-.15 and g['bounds'][2] <= box['x']+box['w']+.15
                and g['y'] + .24*g['pt']*PT_MM <= box['y']+box['h']+.04
                for row in rows for g in row
            )
            metrics[box['id']] = {
                'baselines': [Counter(round(g['y'],4) for g in row).most_common(1)[0][0]
                              for row in rows],
                'visualGlyphs': [''.join(g['char'] for g in sorted(row,key=lambda g:g['x'])) for row in rows],
                'logicalSpans': [''.join(g['char'] for g in row) for row in rows],
                'widths': [[min(g['bounds'][0] for g in row),max(g['bounds'][2] for g in row)] for row in rows],
                'fit': fit, 'fonts':sorted({g['font'] for row in rows for g in row}),
                'sizes':sorted({round(g['pt'],3) for row in rows for g in row}),
                'missingGlyphs':sum(g['glyph']==0 or g['char']=='\ufffd' for row in rows for g in row)
            }
        paths = page.get_drawings()
        magenta = (231/255,23/255,170/255)
        box_edges = [[v*PT_MM for v in p['rect']] for p in paths if close(p['color'],magenta)]
        bg_drawings = [[v*PT_MM for v in p['rect']] for p in paths
            if not close(p['color'],magenta) and page.rect.intersects(p['rect']) and p['rect'].width > 5]
        images = [[v*PT_MM for v in p['bbox']] for p in page.get_image_info()]
        return dict(page=[page.rect.width*PT_MM,page.rect.height*PT_MM], pages=len(doc),
            fonts=[p[3] for p in page.get_fonts(full=True)], overlay=metrics,
            boxEdges=box_edges, backgroundVectors=bg_drawings, imageBounds=images,
            text=page.get_text(), links=len(page.get_links()), bytes=path.stat().st_size)


def isolated_pdf_data(path, config):
    probe = subprocess.run([sys.executable, '-B', str(HERE/'run.py'), '--extract',
        str(path)], input=json.dumps(config,ensure_ascii=False), text=True, capture_output=True,
        timeout=15)
    if probe.returncode != 0:
        return {'measurementError':f'PyMuPDF subprocess exit {probe.returncode}'}
    return json.loads(probe.stdout)


def max_delta(expected, actual):
    if len(expected) != len(actual) or not expected:
        return None
    return round(max(abs(a-b) for a,b in zip(expected,actual)), 5)


def align_bounds(a,b):
    if not a or len(a) != len(b):
        return None
    return max_delta([n for rect in a for n in rect], [n for rect in b for n in rect])


def line_stats(browser):
    measure = browser['iterations']['content'][-1]
    rows = grouped(measure['words'], lambda word: word[2], threshold=1.3)
    return [' '.join(x[0] for x in sorted(row,key=lambda word:word[1])) for row in rows]


def wrap_boundaries(metrics, config):
    measured = metrics['iterations']['content'][-1]
    grouped_words = grouped(measured['words'], lambda word:word[2], threshold=1.3)
    breaks = {}
    for row, next_row in zip(grouped_words, grouped_words[1:]):
        last = max(row, key=lambda word:word[5:])
        next_first = min(next_row, key=lambda word:word[5:])
        p, run, offset = next_first[5:]
        if p != last[5] or any(r.get('break') for r in
            config['boxes'][0]['paragraphs'][p][last[6]+1:run+1]):
            continue
        breaks.setdefault(p,{}).setdefault(run,[]).append(offset)
    for runs in breaks.values():
        for key, offsets in runs.items():
            runs[key] = [b-a for a,b in zip([0]+offsets,offsets)]
    return {'content':breaks}


def prepare(args, english=False):
    args.output.mkdir(parents=True, exist_ok=True)
    for folder in ('pdf','preview','mpdf-tmp','fonts'):
        (args.output/folder).mkdir(exist_ok=True)
    shutil.copyfile(HERE/'browser.html',args.output/'browser.html')
    for target, source in FILES.items():
        shutil.copyfile(args.fonts/source,args.output/'fonts'/f'{target}.ttf')
    env = dict(os.environ, NODE_PATH=str(args.node_modules))
    subprocess.run([str(args.node_modules/'.bin/esbuild'),str(HERE/'browser.jsx'),
        '--bundle','--minify',f'--outfile={args.output/"browser.js"}'],env=env,check=True)
    return build(args.output,args.source,english=english)


def main():
    p=argparse.ArgumentParser()
    for flag in ('source','output','fonts','node-modules'):
        p.add_argument('--'+flag,type=Path,required=True)
    p.add_argument('--chromium',default='/usr/bin/chromium')
    p.add_argument('--php',default='php')
    p.add_argument('--filter', default='.*')
    p.add_argument('--english', action='store_true')
    args=p.parse_args()
    cases=prepare(args,english=args.english)
    results={}
    network=[]
    fonts={}
    for style in FILES:
        font = TTFont(args.output/'fonts'/f'{style}.ttf')
        chars = font.getBestCmap()
        fonts[style] = {c:ord(c) in chars for c in 'سضَّ٠أA'}

    def render(config, sizes, mode, filename, breaks=None):
        job = args.output/'job.json'
        job.write_text(json.dumps(dict(config=config,sizes=sizes,mode=mode,breaks=breaks),
                  ensure_ascii=False),encoding='utf-8')
        destination=args.output/'pdf'/f'{filename}.pdf'
        with tempfile.TemporaryDirectory(prefix='pdf-probe-', dir=args.output/'mpdf-tmp') as scratch:
            scratch_job = Path(scratch)/job.name
            shutil.copyfile(job, scratch_job)
            # The PHP process is bounded by the wrapper's memory limit and parent timeout.
            reply=subprocess.run([args.php,str(HERE/'render.php'),str(scratch_job),
                str(args.output),str(destination)],text=True,capture_output=True,check=True,timeout=15)
        result=json.loads(reply.stdout)
        if reply.stderr.strip():
            result['stderr']=reply.stderr.strip()
        if result['written']:
            result['pdf']=isolated_pdf_data(destination,config)
        return result

    with sync_playwright() as playwright:
        browser=playwright.chromium.launch(executable_path=args.chromium,args=['--no-sandbox'])
        context=browser.new_context(viewport={'width':1440,'height':1300},device_scale_factor=2)
        def local(route):
            url=urlsplit(route.request.url)
            if url.netloc != 'fixture.test':
                network.append(route.request.url)
                route.abort()
                return
            asset=(args.output / url.path.lstrip('/')).resolve()
            if asset.is_relative_to(args.output.resolve()) and asset.is_file():
                route.fulfill(path=asset,content_type=mimetypes.guess_type(asset)[0] or 'application/octet-stream')
            else:
                route.abort()
        context.route('**/*',local)
        tab=context.new_page()
        tab.goto('http://fixture.test/browser.html')
        for config in cases:
            id=config['id']
            if not re.search(args.filter,id):
                continue
            result=results[id]={}
            sizes={b['id']:b['size'] for b in config['boxes']}
            if config.get('rejection'):
                result['reject']=render(config,sizes,'independent',id)
                print(id, result['reject'].get('error','UNEXPECTED SUCCESS'),flush=True)
                continue
            web=tab.evaluate('window.evaluateFixture',config)
            (args.output/'preview').mkdir(exist_ok=True)
            tab.locator('#sheet').screenshot(path=str(args.output/'preview'/f'{id}.png'))
            tab.pdf(path=str(args.output/'pdf'/f'{id}-chromium.pdf'),prefer_css_page_size=True,print_background=True)
            result['browser']=web
            box=config['boxes'][0]
            result['browserLines']=line_stats(web)
            result['chromiumPrint']=isolated_pdf_data(args.output/'pdf'/f'{id}-chromium.pdf',config)
            attempts=[]
            while True:
                pdf=render(config,sizes,'independent',id)
                attempts.append(dict(pt=sizes['content'],written=pdf['written'],
                          fit=pdf.get('pdf',{}).get('overlay',{}).get('content',{}).get('fit'),
                          elapsedMs=pdf['elapsedMs'],phpPeakBytes=pdf['phpPeakBytes']))
                if attempts[-1]['fit'] or not box['fit'] or sizes['content'] <= box['min'] or not pdf['written']:
                    break
                sizes['content']=max(box['min'], sizes['content']-.5)
            result['mpdf']=pdf
            result['mpdfAttempts']=attempts
            if config['id'] in ['en-short','en-long','paired-short','paired-long','ar-short','ar-mixed','ar-run-boundary','fit-arabic','fit-floor-legal']:
                result['tuned']=render(config,web['sizes'],'tuned',id+'-tuned')
                breaks=wrap_boundaries(web,config)
                result['sharedBreaks']=render(config,web['sizes'],'shared',id+'-shared',breaks)
                result['breakInstructions']=breaks
            if config['id'] in ['en-long','ar-mixed']:
                zoom=context.new_page()
                zoom.set_viewport_size({'width':870,'height':800})
                zoom.goto('http://fixture.test/browser.html?zoom=0.65')
                result['alternateViewport']=zoom.evaluate('window.evaluateFixture',config)
                result['alternateViewportLines']=line_stats(result['alternateViewport'])
                zoom.close()
            if pdf['written'] and 'overlay' in pdf['pdf']:
                output=pdf['pdf']
                data=output['overlay']['content']
                expected_boxes=[web['iterations'][b['id']][-1]['bounds'] for b in config['boxes']]
                print_overlay=result['chromiumPrint'].get('overlay',{}).get('content',{})
                equivalent_glyph_lines = print_overlay.get('visualGlyphs') == data['visualGlyphs'] if print_overlay else None
                result['comparison']={'pageDeltaMm':max_delta(config['page'],output['page']),
                    'boxDeltaMm':align_bounds(expected_boxes,output['boxEdges']),
                    'browserFit':web['iterations']['content'][-1]['fit'], 'pdfFit':data['fit'],
                    'browserLineCount':len(result['browserLines']), 'pdfLineCount':len(data['baselines']),
                    'visualGlyphLinesMatchPrint':equivalent_glyph_lines,
                    'browserExpectedFit': config['expected_fit'],
                    'fontDeltaPt':max_delta([web['sizes']['content']], data['sizes']),
                    'firstBaselineDeltaMm':max_delta(web['iterations']['content'][-1]['firstParagraphBaselines'][:1],
                                                    data['baselines'][:1]),
                    'maxBaselineDeltaWithChromiumPrintMm':max_delta(print_overlay.get('baselines',[]),
                                                    data['baselines'])}
            # Preserve overflowing output as diagnostics only; refuse approved artifacts.
            if 'comparison' in result and result['comparison']['pdfFit'] and data['missingGlyphs'] == 0:
                result['artifactDecision'] = 'meets local overflow and missing-glyph check'
            else:
                result['artifactDecision'] = 'REJECTED, PDF retained for diagnostic measurements only'
            print(id,result.get('comparison',pdf.get('error')),flush=True)
        browser_ver=browser.version
        browser.close()
    result=dict(browserVersion=browser_ver, fontCoverage=fonts, unexpectedBrowserNetwork=network, cases=results)
    (args.output/'evidence.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
    rows=[dict(case=k,**v['comparison']) for k,v in results.items() if 'comparison' in v]
    if rows:
        with (args.output/'comparisons.csv').open('w',encoding='utf-8',newline='') as f:
            writer=csv.DictWriter(f,fieldnames=list(rows[0]))
            writer.writeheader()
            writer.writerows(rows)
    (args.output/'job.json').unlink(missing_ok=True)


if __name__=='__main__':
    if len(sys.argv) == 3 and sys.argv[1] == '--extract':
        print(json.dumps(pdf_data(Path(sys.argv[2]), json.load(sys.stdin)),ensure_ascii=False))
    else:
        main()
