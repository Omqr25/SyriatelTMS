"""Build bounded synthetic configurations; no database or application bootstrap."""
import json
from pathlib import Path
import shutil

import fitz
from PIL import Image

HERE = Path(__file__).resolve().parent


def text(value, **marks):
    return dict(text=value, **marks)


def token(value, **marks):
    return dict(token=value, **marks)


def box(id, x, y, w, h, size, align, direction, paragraphs, **options):
    return dict(id=id, x=x, y=y, w=w, h=h, size=size, align=align,
                direction=direction, paragraphs=paragraphs, min=options.pop('min', size),
                fit=options.pop('fit', False), **options)


def build(output, source, english=False):
    fixtures = output / 'fixtures'
    fixtures.mkdir(parents=True, exist_ok=True)
    original_pdf = fixtures / 'as-supplied.pdf'
    shutil.copyfile(source, original_pdf)
    src = fixtures / 'supplied-a4.pdf'
    # The input includes an associated file. A page-only copy is a synthetic
    # test derivative, never an upload bypass or proposed runtime sanitizer.
    with fitz.open(original_pdf) as original, fitz.open() as static:
        static.insert_pdf(original, links=False, annots=False, widgets=False)
        static.save(src)
    with fitz.open(src) as doc:
        original = doc[0]
        vector = original.get_pixmap(matrix=fitz.Matrix(2, 2), alpha=False)
        vector.save(fixtures / 'supplied-a4-preview.png')
    with fitz.open() as doc, fitz.open(src) as original:
        doc.insert_pdf(original)
        doc[0].set_cropbox(fitz.Rect(15, 20, 575, 820))
        doc[0].set_rotation(90)
        doc.save(fixtures / 'cropped-rotated.pdf')
    with fitz.open(fixtures / 'cropped-rotated.pdf') as doc:
        doc[0].get_pixmap(matrix=fitz.Matrix(2, 2)).save(fixtures / 'cropped-rotated-preview.png')
    for label, size in {'portrait': (1400, 1980), 'landscape': (1980, 1400)}.items():
        from PIL import ImageDraw
        image = Image.new('RGB', size, 'white')
        painter = ImageDraw.Draw(image)
        painter.rectangle((35, 35, size[0]-35, size[1]-35), outline='#273b66', width=4)
        painter.rectangle((50, 50, size[0]-50, size[1]-50), outline='#bb9d68', width=3)
        painter.line((100, 340, size[0]-100, 340), fill='#bb9d68', width=3)
        painter.rectangle((100, 120, 138, 158), fill='#13995a')
        painter.rectangle((size[0]-138, size[1]-200, size[0]-100, size[1]-162), fill='#13995a')
        image.save(fixtures / f'{label}.png')
        image.save(fixtures / f'{label}.jpg', quality=92, subsampling=0)
    with fitz.open(src) as doc:
        doc.save(fixtures / 'encrypted.pdf', encryption=fitz.PDF_ENCRYPT_AES_256, owner_pw='offline-fixture')
        doc.save(fixtures / 'object-stream.pdf', use_objstms=1, deflate=True)
        with fitz.open(src) as original:
            doc.insert_pdf(original, from_page=0, to_page=0)
        doc.save(fixtures / 'two-pages.pdf')
    (fixtures / 'broken.pdf').write_bytes(b'%PDF-1.4\n1 0 obj\n<<\n')
    (fixtures / 'broken.png').write_bytes(b'\x89PNG\r\n\x1a\ntruncated')
    shutil.copyfile(fixtures / 'portrait.png', fixtures / 'disguised.pdf')
    with (fixtures / 'large.pdf').open('wb') as file:
        file.truncate(5 * 1024 * 1024 + 1)
    import struct, zlib
    header = struct.pack('>IIBBBBB', 9000, 9000, 8, 2, 0, 0, 0)
    chunk_type = b'IHDR'
    (fixtures / 'oversized.png').write_bytes(b'\x89PNG\r\n\x1a\n' + struct.pack('>I', len(header)) +
        chunk_type + header + struct.pack('>I', zlib.crc32(chunk_type + header)))
    with fitz.open(src) as doc:
        catalog = doc.pdf_catalog()
        trigger = doc.get_new_xref()
        doc.update_object(trigger, '<< /S /JavaScript /JS (/* inert synthetic fixture */) >>')
        doc.xref_set_key(catalog, 'OpenAction', f'{trigger} 0 R')
        doc.save(fixtures / 'active-action.pdf')
        doc.xref_set_key(catalog, 'OpenAction', 'null')
        doc[0].insert_link({'kind': fitz.LINK_URI, 'from': fitz.Rect(10,10,50,25),
                            'uri': 'https://example.invalid/'})
        doc.save(fixtures / 'external-link.pdf')

    en = [[text('This is to certify that '), token('trainee_name', bold=True),
           text(' participated in '), token('program_name', bold=True),
           text(' and completed training in '), token('department_name', italic=True),
           text(' for two months.'), {'break': True},
           text('We wish them continued success.', italic=True)],
          [text('Training period: '), token('program_start_date'), text(' to '), token('program_end_date'), text('.')]]
    ar = [[text('نشهد بأنّ '), token('trainee_name', bold=True),
           text(' قد أتمّ التدريبَ في '), token('department_name'),
           text(' ضمن برنامج '), token('program_name', bold=True),
           text('، مع تمنّياتنا له بالتوفيق.'), {'break': True},
           text('الفترة من '), token('program_start_date'), text(' إلى '), token('program_end_date'), text('.')]]
    mixed = [[text('نشهد بأنّ '), token('trainee_name', bold=True), text(' (2026)، وأنه شارك في '),
              token('program_name', bold=True), text(' لدى '), token('department_name', italic=True),
              text(' بتاريخ '), token('issue_date'), text('؛ مع تمنّياتنا بالتوفيق.'), {'break': True},
              text('Certificate no. ', italic=True), token('certificate_number')]]
    values = dict(trainee_name='Nora Vale', program_name='Synthetic Training',
                  department_name='Research', program_start_date='2026-09-10', program_end_date='2026-11-30',
                  issue_date='2026-12-01', certificate_number='CERT-2026-000001')
    long = dict(trainee_name='Nora Alexandra Valentina Vale Richardson',
                department_name='Applied Language and Training Operations Department')
    arabic = dict(trainee_name='نُور السّلام', program_name='برنامج التّدريب', department_name='قسم الأبحاث')
    ar_long = dict(trainee_name='نُور الهدى عبد السّلام بنت عبد الرّحمن الأمين',
                   department_name='قسم التّخطيط والتّطوير المهني والتّقنيات التّطبيقيّة')
    ar_mixed = dict(trainee_name='نُور Vale عبد السّلام', program_name='Synthetic TMS',
                    department_name='Applied Research')
    cases = []

    def add(id, content=en, mapping=None, dir='ltr', align='left', dimensions=(210,297),
            bg='supplied-a4.pdf', width=148, height=70, size=15, fit=False, minimum=None,
            number=True, expected_fit=True, **extras):
        w, h = dimensions
        boxes = [
            box('content', (w-width)/2, 111 if h>230 else 67, width, height, size, align, dir, content,
                fit=fit, min=size if minimum is None else minimum),
            box('date', 22, h-51, 56, 11, 11, 'left', 'ltr',
                [[text('Issue: '), token('issue_date')]]),
        ]
        if number:
            boxes.append(box('number', w-85, h-51, 64, 11, 10, 'right', 'ltr',
                         [[token('certificate_number', italic=True)]]))
        cases.append(dict(id=id, page=list(dimensions), background=bg, boxes=boxes,
                          values={**values, **(mapping or {})}, expected_fit=expected_fit, **extras))

    add('en-short')
    add('en-long', mapping=long, width=135)
    add('paired-short', width=88, height=85)
    add('paired-long', mapping=long, width=88, height=85)
    add('en-center', mapping=long, align='center', number=False, bg='portrait.jpg')
    add('en-right', align='right', bg='portrait.png')
    add('en-justify', mapping=long, align='justify', width=135)
    add('ar-short', ar, arabic, 'rtl', 'right')
    add('ar-long', ar, {**arabic, **ar_long}, 'rtl', 'right', width=132)
    add('ar-justify', ar, {**arabic, **ar_long}, 'rtl', 'justify', width=102)
    add('ar-mixed', mixed, ar_mixed, 'rtl', 'right', width=135)
    add('en-mixed', mixed, ar_mixed, 'ltr', 'left', width=135)
    add('ar-italic', [[text('سلامٌ '), token('trainee_name', italic=True),
                      text(' ولَا يزال التّدريب مستمرًّا. ', bold=True, italic=True),
                      text('لاسلكي '), text('مستمرّ')]], arabic, 'rtl', 'right')
    add('ar-run-boundary', [[text('س'), text('لام', bold=True), text('ٌ، ١٢٣ (L1). '),
                             text('ال'), text('تّدريب', italic=True)]], arabic, 'rtl', 'right')
    if english:
        cases[:] = [case for case in cases if case['id'] not in
                    {'ar-short','ar-long','ar-justify','ar-mixed','en-mixed','ar-italic','ar-run-boundary'}]
        next(case for case in cases if case['id']=='paired-long')['expected_fit'] = False
        add('positive-narrow-long', mapping=long, width=88, height=105)
        three_lengths = dict(trainee_name='Nora Alexandra-Valentina Vale Richardson',
            program_name='Early Career Telecommunications Network Operations and Digital Skills Programme',
            department_name='Advanced Applied Language Engineering and Customer Operations Department')
        add('all-long', mapping=three_lengths, width=124, height=105, number=False)
        add('program-only-long', mapping={'program_name':three_lengths['program_name']}, width=105, height=85)
        add('department-only-long', mapping={'department_name':three_lengths['department_name']}, width=105, height=85)
        add('name-only-long', mapping={'trainee_name':three_lengths['trainee_name']}, width=105, height=85)
        add('serif-marks', [[text('AV office: “'), token('trainee_name', bold=True, italic=True),
             text('” and François O’Neill, '), token('department_name', italic=True), {'break':True},
             text('AVANT, WA and Ta'), {'break':True}, {'break':True},
             text('Affiliation — '), token('program_name', bold=True)]], width=98, height=90, size=17)
        cases[-1]['boxes'][0].update(font='serif', color='#7d2858', leading=1.48)
        add('hyphen-and-runs', [[text('A '), text('tele', bold=True, italic=True),
            text('communications-service'), text(' for '), token('trainee_name', bold=True),
            text(' — '), token('program_name', italic=True)]], mapping=three_lengths, width=82, height=95, align='justify')
        add('unbreakable-overflow', [[token('trainee_name', italic=True)]],
            mapping={'trainee_name':'W'*48}, width=68, height=32, size=18, fit=True, minimum=12, expected_fit=False)
        add('legal-minimum', [[text('AVA', bold=True, italic=True)]], width=36, height=5.60,
            size=25, fit=True, minimum=12)
        cases[-1]['boxes'][0]['leading'] = 1.32
        add('below-minimum', [[text('AVA', bold=True, italic=True)]], width=36, height=5.57,
            size=25, fit=True, minimum=12, expected_fit=False)
        add('small-size', size=7.5, width=55, height=80)
        add('wide-large-size', [[text('Qualified ', bold=True), token('trainee_name', italic=True)]],
            size=31.5, width=170, height=50)
    add('fit-success', mapping=long, width=110, height=46, size=22, fit=True, minimum=12)
    add('fit-floor-legal', mapping=long, width=110, height=42, size=20, fit=True, minimum=12)
    add('fit-fixed-overflow', mapping=long, width=110, height=35, size=22, expected_fit=False)
    add('fit-floor-overflow', mapping=long, width=110, height=11, size=22, fit=True, minimum=18,
        expected_fit=False)
    if not english:
        add('fit-arabic', ar, {**arabic, **ar_long}, 'rtl', 'right', width=105, height=39,
        size=20, fit=True, minimum=12)
    add('landscape-png', dimensions=(297,210), bg='landscape.png', align='center')
    add('landscape-jpg', dimensions=(297,210), bg='landscape.jpg')
    with fitz.open(fixtures/'cropped-rotated.pdf') as doc:
        dims = [d*25.4/72 for d in (doc[0].rect.width, doc[0].rect.height)]
    add('crop-rotation', dimensions=dims, bg='cropped-rotated.pdf')
    add('escaped-content', [[text('<img src="https://example.invalid/fake"> '),
                             token('trainee_name'), text(' is literal.')]],
        dict(trainee_name='{{salutation}} <b>Sample</b>'))
    for name, kind in [('as-supplied.pdf','attachments'), ('broken.pdf','malformed'), ('broken.png','malformed'), ('disguised.pdf','mime'),
                       ('large.pdf','size'), ('oversized.png','dimensions'), ('active-action.pdf','active'),
                       ('external-link.pdf','links'), ('two-pages.pdf','page count'),
                       ('encrypted.pdf','encrypted'), ('object-stream.pdf','unsupported structures')]:
        add('reject-'+name.split('.')[0]+('-image' if name=='broken.png' else ''), bg=name,
            rejection=kind)
    for name, content, mapping in [
        ('unknown-token', [[token('salutation')]], {}),
        ('missing-value', [[token('trainee_name')]], {'trainee_name': ''}),
        ('excess-text', [[text('a' * 2049)]], {}),
        ('arbitrary-html-node', [[{'html':'<img src="https://example.invalid/">'}]], {}),
    ]:
        add('reject-'+name, content, mapping, rejection='text config')
    (output / 'cases.json').write_text(json.dumps(cases, ensure_ascii=False, indent=2), encoding='utf-8')
    return cases
