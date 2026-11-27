"""Measure background artwork placement from rendered samples in physical units."""
import json
from pathlib import Path
import sys

import fitz
from PIL import Image, ImageChops

PT_MM = 25.4 / 72


def vector_background(source, output, config):
    with fitz.open(source) as src, fitz.open(output) as out:
        original=src[0]
        generated=out[0]
        transform=original.rotation_matrix * fitz.Matrix(
            generated.rect.width/original.rect.width, generated.rect.height/original.rect.height)
        expected=[x['rect']*transform for x in original.get_drawings()]
        actual=[x['rect'] for x in generated.get_drawings() if not (x.get('color') and
            all(abs(a-b)<.01 for a,b in zip(x['color'], (231/255,23/255,170/255))))]
        deltas=[min(max(abs(a-b) for a,b in zip(shape, rect)) for rect in actual)*PT_MM
                for shape in expected if generated.rect.intersects(shape)]
        return {'matchedPaths':len(deltas),'maximumVectorEdgeDeltaMm':max(deltas),
            'originalWasSinglePage':len(src)==1,'generatedImageCount':len(generated.get_images()),
            'generatedTextPresent':bool(generated.get_text()),'pageCount':len(out)}


def screen_band(path, config, svg=False):
    image=Image.open(path).convert('RGB')
    return image.resize((round(config['page'][0]*8),round(config['page'][1]*8)))


def registration_artwork(browser, generated, config):
    # The four raster fixture types contain two high-contrast green fiducials.
    # Normalize each rendering to 8 samples/mm before finding their pixel bounds.
    screen=screen_band(browser,config)
    with fitz.open(generated) as doc:
        pix=doc[0].get_pixmap(matrix=fitz.Matrix(8*PT_MM,8*PT_MM))
        pdf=Image.frombytes('RGB',(pix.width,pix.height),pix.samples).resize(screen.size)
    def centroids(image):
        r,g,b=image.split()
        mask=ImageChops.multiply(g.point(lambda v:255 if v>100 else 0),
            ImageChops.multiply(r.point(lambda v:255 if v<75 else 0),
                                b.point(lambda v:255 if v<140 else 0)))
        h = mask.height//2
        rectangles=[mask.crop((0,0,mask.width,h)).getbbox(),
                    mask.crop((0,h,mask.width,mask.height)).getbbox()]
        return [[(box[0]+box[2])/16,(box[1]+box[3]+2*dy)/16]
                for box,dy in zip(rectangles,(0,h))]
    a,b=centroids(screen),centroids(pdf)
    return {'browserFiducialsMm':a,'pdfFiducialsMm':b,
            'maximumFiducialDeltaMm':max(max(abs(x-y) for x,y in zip(p,q)) for p,q in zip(a,b))}


root=Path(sys.argv[1])
cases=json.loads((root/'cases.json').read_text())
results={}
for config in cases:
    id=config['id']
    if id in ['en-short','crop-rotation']:
        results[id]=vector_background(root/'fixtures'/config['background'],root/'pdf'/f'{id}.pdf',config)
    if id in ['en-center','en-right','landscape-png','landscape-jpg']:
        results[id]=registration_artwork(root/'preview'/f'{id}.png',root/'pdf'/f'{id}.pdf',config)
(root/'background-measurements.json').write_text(json.dumps(results,indent=2))
print(json.dumps(results,indent=2))
