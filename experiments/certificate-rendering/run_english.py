"""Compare browser SVG and mPDF positioned text from independently calculated layout."""
import argparse
import csv
import hashlib
import json
import mimetypes
import os
from pathlib import Path
import shutil
import subprocess
import time
from urllib.parse import urlsplit

import fitz
from PIL import Image, ImageChops
from playwright.sync_api import sync_playwright

from fixtures import build
from font_metrics import FAMILIES, read_face
from run import HERE, PT_MM, align_bounds, close, max_delta, prepare

RENDERER_ARGUMENTS = ('source','output','fonts','node-modules')
EXPECTED_REJECTIONS = {
    'reject-as-supplied':'catalog features disallowed',
    'reject-broken':'xref table',
    'reject-broken-image':'MIME or extension mismatch',
    'reject-disguised':'MIME or extension mismatch',
    'reject-large':'file size above the probe budget',
    'reject-oversized':'decoded pixel budget',
    'reject-active-action':'OpenAction',
    'reject-external-link':'annotations disallowed',
    'reject-two-pages':'exactly one PDF page',
    'reject-encrypted':'encrypted',
    'reject-object-stream':'not supported by the free parser',
    'reject-unknown-token':'invalid placeholder',
    'reject-missing-value':'invalid placeholder',
    'reject-excess-text':'invalid or oversized text node',
    'reject-arbitrary-html-node':'invalid or oversized text node',
}



def rgb(hex_color):
    return tuple(int(hex_color[i:i+2],16)/255 for i in (1,3,5))


def positioned_pdf(path, config, placement):
    with fitz.open(path) as doc:
        page = doc[0]
        traces = page.get_texttrace()
        boxes = {}
        for box in config['boxes']:
            expected = placement[box['id']]
            rows = [[] for line in expected['lines']]
            color = rgb(box.get('color','#123ca2'))
            for trace in traces:
                if not close(trace['color'],color):
                    continue
                for code, glyph, origin, bounds in trace['chars']:
                    if code < 0 or chr(code).isspace():
                        continue
                    x,y = [c*PT_MM for c in origin]
                    if not (box['x']-.05<=x<=box['x']+box['w']+.05 and
                            box['y']-.05<=y<=box['y']+box['h']+.05):
                        continue
                    number = min(range(len(rows)),key=lambda i:abs(expected['lines'][i]['y']-y))
                    rows[number].append(dict(char=chr(code), glyph=glyph,x=x,y=y,
                        pt=trace['size'],font=trace['font'],fontColor=trace['color']))
            boxes[box['id']] = rows
        edges = [[p*PT_MM for p in item['rect']] for item in page.get_drawings()
                 if close(item['color'],rgb('#e717aa'))]
        return dict(pages=len(doc),page=[page.rect.width*PT_MM,page.rect.height*PT_MM],
            edges=edges,boxes=boxes,text=page.get_text(),links=len(page.get_links()),
            embeddedImages=len(page.get_images()), bytes=path.stat().st_size)


def require_all_input(box, values, lines):
    source = ''.join(node.get('text', values.get(node.get('token'), ''))
        for paragraph in box['paragraphs'] for node in paragraph)
    actual = ''.join(glyph['char'] for line in lines for glyph in line)
    assert ''.join(source.split()) == ''.join(actual.split()), 'Resolved content was omitted or rewritten'


def ink_checks(preview, path, config):
    # Compare the actual overlay ink in a 1 mm neighborhood around each box.
    # This uses 300 dpi on the PDF and normalizes preview screenshots to that grid.
    factor = 300/25.4
    screen = Image.open(preview).convert('RGB')
    measurements = []
    with fitz.open(path) as doc:
        for box in config['boxes']:
            x, y = box['x'], box['y']
            area = [x-1,y-1,x+box['w']+1,y+box['h']+1]
            clip = fitz.Rect(*(v/PT_MM for v in area))
            matrix = fitz.Matrix(300/72,300/72)
            pixels = doc[0].get_pixmap(matrix=matrix,clip=clip,alpha=False)
            paper = Image.frombytes('RGB',(pixels.width,pixels.height),pixels.samples)
            window = screen.crop(tuple(round(v*screen.size[i%2]/config['page'][i%2])
                                       for i,v in enumerate(area))).resize(paper.size)
            color = tuple(round(v*255) for v in rgb(box.get('color','#123ca2')))
            def bounds(image):
                channels = [channel.point(lambda v: 255 if abs(v-expected)<30 else 0)
                            for channel,expected in zip(image.split(),color)]
                ink = ImageChops.multiply(channels[0],ImageChops.multiply(*channels[1:]))
                return ink.getbbox()
            a,b = bounds(window),bounds(paper)
            assert a and b, f"No overlay ink detected in {box['id']}"
            assert max(abs(left-right)/factor for left,right in zip(a,b)) <= .5, (box['id'],a,b)
            for ink in (a,b):
                assert ink[0]/factor >= .8 and ink[1]/factor >= .8
                assert ink[2]/factor <= box['w'] + 1.2 and ink[3]/factor <= box['h'] + 1.2
            measurements.append(dict(box=box['id'],inkEdgeDeltaMm=max(abs(a-b)/factor for a,b in zip(a,b))))
    return measurements


def compare(browser,pdf,config):
    glyph_data=[]
    for box in config['boxes']:
        a,b=browser['boxes'][box['id']]['lines'],pdf['boxes'][box['id']]
        require_all_input(box,config['values'],b)
        if len(a)!=len(b) or not all(len(x)==len(y) for x,y in zip(a,b)):
            raise AssertionError(f"Glyph row counts in {box['id']} differ")
        for left,right in zip(a,b):
            if [g['char'] for g in left]!=[g['char'] for g in right]:
                raise AssertionError(f"Wrong line contents or glyph order in {box['id']}")
            glyph_data.extend((x,y,box) for x,y in zip(left,right))
    for a,b,box in glyph_data:
        style=a['mark']
        suffix={'R':'','B':'-Bold','I':'-Italic','BI':'-BoldItalic'}[style]
        family='DejaVuSerif' if box.get('font')=='serif' else 'DejaVuSans'
        if family=='DejaVuSans' and 'I' in style:
            suffix=suffix.replace('Italic','Oblique')
        if not b['font'].endswith(family+suffix) or b['glyph']==0:
            raise AssertionError(f"Missing glyph or incorrect actual PDF face {a} {b}")
        if a['family'].strip('"')!=f"{box.get('font','sans')}-{style}" or not close(b['fontColor'],rgb(box.get('color','#123ca2'))):
            raise AssertionError('Font variant or color changed')
    page_delta=max_delta(browser['page'],pdf['page'])
    box_delta=align_bounds([v['edges'] for v in browser['boxes'].values()],pdf['edges'])
    glyph_x=max(abs(a['x']-b['x']) for a,b,_ in glyph_data)
    baseline=max(abs(a['y']-b['y']) for a,b,_ in glyph_data)
    size=max(abs(a['pt']-b['pt']) for a,b,_ in glyph_data)
    assert pdf['pages']==1 and pdf['links']==0
    assert page_delta<=.25 and box_delta<=.5 and baseline<=.5 and size<=.25 and glyph_x<=.5
    return dict(pageDeltaMm=page_delta,boxDeltaMm=box_delta,baselineDeltaMm=baseline,fontDeltaPt=size,
        horizontalGlyphDeltaMm=glyph_x,glyphs=len(glyph_data),
        lines=len(browser['boxes']['content']['lines']))


def family_contract(args):
    contract = {}
    hashes = {}
    for family, variants in FAMILIES.items():
        contract[family] = {}
        for mark, file in variants.items():
            name=f'{family}-{mark}.ttf'
            src=args.fonts/file
            shutil.copyfile(src,args.output/'fonts'/name)
            contract[family][mark]=read_face(src)
            hashes[name]=hashlib.sha256(src.read_bytes()).hexdigest()
    (args.output/'font-metrics.json').write_text(json.dumps(contract,ensure_ascii=False))
    (args.output/'font-checksums.json').write_text(json.dumps(hashes,indent=2))
    return contract


def main():
    parser=argparse.ArgumentParser()
    for flag in RENDERER_ARGUMENTS:
        parser.add_argument('--'+flag,type=Path,required=True)
    parser.add_argument('--chromium',default='/usr/bin/chromium')
    parser.add_argument('--php',default='php')
    parser.add_argument('--filter',default='')
    args=parser.parse_args()
    # Preserve the original renderer and input assets for paired comparisons.
    prepare(args,english=True)
    contract=family_contract(args)
    shutil.copyfile(HERE/'positioned.html',args.output/'positioned.html')
    subprocess.run([str(args.node_modules/'.bin/esbuild'),str(HERE/'positioned.jsx'),'--bundle','--minify',
        f'--outfile={args.output/"positioned.js"}'],
        env=dict(os.environ,NODE_PATH=str(args.node_modules)),check=True)
    cases=json.loads((args.output/'cases.json').read_text())
    results={}
    failures=[]
    requests=[]
    with sync_playwright() as pw:
        engine = pw.chromium.launch(executable_path=args.chromium,args=['--no-sandbox'])
        context=engine.new_context(viewport={'width':1440,'height':1300},device_scale_factor=2)
        def route_fixture(route):
            url=urlsplit(route.request.url)
            if url.netloc!='fixture.test':
                requests.append(route.request.url)
                route.abort()
                return
            path=(args.output/url.path.lstrip('/')).resolve()
            if path.is_relative_to(args.output.resolve()) and path.is_file():
                route.fulfill(path=path,content_type=mimetypes.guess_type(path)[0] or 'application/octet-stream')
            else:
                route.abort()
        context.route('**/*',route_fixture)
        page=context.new_page()
        page.goto('http://fixture.test/positioned.html')
        for config in cases:
            id=config['id']
            if args.filter and args.filter not in id:
                continue
            rec=results[id]={}
            try:
                destination=args.output/'pdf'/f'{id}.pdf'
                rec['layoutMode']='positioned'
                job=args.output/'job.json'
                job.write_text(json.dumps({'config':config,'mode':'positioned'},ensure_ascii=False))
                start=time.monotonic()
                php=subprocess.run([args.php,str(HERE/'render.php'),str(job),str(args.output),str(destination)],
                    timeout=15,capture_output=True,text=True,check=True)
                parsed=json.loads(php.stdout)
                rec['server']=parsed
                rec['phpProcessMs']=(time.monotonic()-start)*1000
                if php.stderr.strip():
                    raise AssertionError(php.stderr.strip())
                if config.get('rejection'):
                    assert not parsed['written'] and not destination.exists(), parsed
                    assert EXPECTED_REJECTIONS[id] in parsed['error'], parsed['error']
                else:
                    browser=page.evaluate('args=>window.positionFixture(...args)',[config, contract])
                    rec['browser']=browser
                    page.locator('#sheet').screenshot(path=args.output/'preview'/f'{id}.png')
                    assert browser['placement']==parsed.get('layout'), 'Independent shared layout outputs differ'
                    assert browser['sheetDirection']=='ltr'
                    assert config['expected_fit']==(not browser['overflow'])==parsed['written'], (browser['overflow'], parsed['error'] if not parsed['written'] else 'PDF written')
                    if not parsed['written']:
                        assert parsed['error']=='Overflow' and not destination.exists()
                        assert browser['placement']['content']['size']>=config['boxes'][0]['min']
                    else:
                        pdf=rec['pdf']=positioned_pdf(destination,config,parsed['layout'])
                        rec['measurements']=compare(browser,pdf,config)
                        if id in {'en-short','serif-marks','all-long','fit-floor-legal','landscape-jpg'}:
                            rec['rasterInk']=ink_checks(args.output/'preview'/f'{id}.png',destination,config)
                        if id=='escaped-content':
                            assert '{{salutation}} <b>Sample</b>' in pdf['text']
                        if id in {'en-long','serif-marks','all-long'}:
                            secondary=context.new_page()
                            secondary.set_viewport_size({'width':740,'height':680})
                            secondary.goto('http://fixture.test/positioned.html?zoom=.65&ui=ar')
                            alt=secondary.evaluate('args=>window.positionFixture(...args)',[config,contract])
                            assert alt['placement']==browser['placement'] and alt['sheetDirection']=='ltr' and alt['uiDirection']=='rtl'
                            rec['alternateViewport']=compare(alt,pdf,config)
                            secondary.locator('#sheet').screenshot(path=args.output/'preview'/f'{id}-zoom-rtl-ui.png')
                            secondary.close()
                rec['result']='pass'
            except (AssertionError,ValueError,KeyError,subprocess.SubprocessError) as exc:
                rec['result']='fail'
                rec['failure']=str(exc) or type(exc).__name__
                failures.append(id)
            print(id,rec.get('failure',rec.get('measurements',rec.get('server',{}).get('error','pass'))),flush=True)
        version=engine.version
        engine.close()
    evidence={'browserEngine':'chromium','browserVersion':version,'externalRequests':requests,'failures':failures,'cases':results}
    (args.output/'evidence.json').write_text(json.dumps(evidence,ensure_ascii=False,indent=2))
    rows=[{'case':id,**v['measurements']} for id,v in results.items() if 'measurements' in v]
    if rows:
        with (args.output/'comparisons.csv').open('w',newline='') as file:
            writer=csv.DictWriter(file,fieldnames=list(rows[0]))
            writer.writeheader()
            writer.writerows(rows)
    print(f'{len(results)-len(failures)} passed, {len(failures)} failed, {len(requests)} external requests',flush=True)
    (args.output/'job.json').unlink(missing_ok=True)
    raise SystemExit(bool(failures or requests))


if __name__=='__main__':
    main()
