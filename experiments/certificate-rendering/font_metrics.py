"""Derive actual Latin advances and ink bounds from the same pinned TTFs used for drawing."""

from fontTools.pens.boundsPen import BoundsPen
from fontTools.ttLib import TTFont

FAMILIES = {
    'sans': {'R':'DejaVuSans.ttf', 'B':'DejaVuSans-Bold.ttf',
             'I':'DejaVuSans-Oblique.ttf', 'BI':'DejaVuSans-BoldOblique.ttf'},
    'serif': {'R':'DejaVuSerif.ttf', 'B':'DejaVuSerif-Bold.ttf',
              'I':'DejaVuSerif-Italic.ttf', 'BI':'DejaVuSerif-BoldItalic.ttf'},
}

# Probe a conservative repertoire. This list is not the production English-name policy.
PROBE_CHARS = list(range(32, 127)) + list(range(160, 384)) + [
    0x2010, 0x2011, 0x2013, 0x2014, 0x2018, 0x2019, 0x201C, 0x201D, 0x2026,
]


def read_face(path):
    with TTFont(path) as font:
        glyphs = font.getGlyphSet()
        cmap = font.getBestCmap()
        names = {cmap[code]: chr(code) for code in PROBE_CHARS if code in cmap}
        units = font['head'].unitsPerEm
        measurements = {}
        for code in PROBE_CHARS:
            if code not in cmap:
                continue
            name = cmap[code]
            pen = BoundsPen(glyphs)
            glyphs[name].draw(pen)
            measurements[chr(code)] = [font['hmtx'][name][0], *(pen.bounds or [0,0,0,0])]
        kerning = {}
        if 'kern' in font:
            for table in font['kern'].kernTables:
                if table.coverage == 1:
                    for (left,right), value in table.kernTable.items():
                        if left in names and right in names:
                            kerning[names[left]+names[right]] = value
        return {'units':units, 'ascent':font['hhea'].ascent, 'descent':-font['hhea'].descent,
                'measurements':measurements, 'kerning':kerning}
