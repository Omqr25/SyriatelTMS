# Certificate rendering proof of concept

The English-only matrix measures a local React preview against server output from mPDF and FPDI. It has no application UI, routes, database connection, or queues. Each browser route is fulfilled from the scratch directory. Other origins are blocked. The harness only processes synthetic data and bounded fixtures. All outputs, caches, and prepared font files belong in a private scratch directory outside the repository.

## Reproduce the English matrix

Use PHP with mbstring, gd, fileinfo, iconv, ctype and XML, the backend Composer dependencies, the frontend npm dependencies, Chromium, and Python with Playwright, Pillow, fonttools and PyMuPDF. The tested versions were mPDF 8.3.1, FPDI 2.6.10, Chromium 156.0.8078.79, React 18.3.1, Node 24.22.1, PHP 8.4.27, PyMuPDF 1.26.6, Playwright 1.62.0, Pillow 13.0.0 and fonttools 4.61.1 on Debian 13.

From this directory in a checkout with the dependencies installed:

```sh
export POC_VENDOR_AUTOLOAD="$(realpath ../../backend/vendor/autoload.php)"
MPDF_FONTS="$(realpath ../../backend/vendor/mpdf/mpdf/ttfonts)"
NODE_MODULES="$(realpath ../../frontend/main/node_modules)"
OUT="$(mktemp -d)"
SOURCE=/absolute/path/Certificate_Blank_Test_Template.pdf
PHP_BIN="$(command -v php)"
CHROMIUM="$(command -v chromium)"

python3 -B run_english.py --source "$SOURCE" --output "$OUT" \
  --fonts "$MPDF_FONTS" --node-modules "$NODE_MODULES" \
  --php "$PHP_BIN" --chromium "$CHROMIUM"
python3 -B background.py "$OUT"
"$PHP_BIN" combine.php "$OUT/pdf/combined-synthetic.pdf" "$OUT/mpdf-tmp" \
  "$OUT/pdf/en-short.pdf" "$OUT/fixtures/supplied-a4.pdf" \
  "$OUT/fixtures/cropped-rotated.pdf"

BASELINE="$(mktemp -d)"
python3 -B run.py --english --source "$SOURCE" --output "$BASELINE" \
  --fonts "$MPDF_FONTS" --node-modules "$NODE_MODULES" \
  --php "$PHP_BIN" --chromium "$CHROMIUM"
```

Use a PHP wrapper that sets `memory_limit=128M` to reproduce the reported allocator peak. This environment used Debian PHP CLI and extensions unpacked in a private scratch directory, with no change to the repository runtime. Each PHP process has a 15-second test timeout. Passing `--filter serif-marks` to `run_english.py` runs a targeted case. Use the complete run for the background checks.

## Rendering contract and evidence

- `font_metrics.py` derives glyph advances, ink bounds and horizontal kerning pairs directly from the eight bundled DejaVu Sans and Serif faces. The serialized metrics and font hashes are written to scratch.
- `layout.js` and `layout.php` independently compute the same physical geometry. They resolve literal placeholders, preserve style marks, wrap deterministically, account for italic overhang, and try paragraph sizes in 0.5 pt steps down to the configured floor. They return positioned glyphs and an explicit overflow state. PHP stops before writing a PDF when any box overflows. These are experimental implementations of a contract that needs parity tests if adopted.
- `positioned.jsx` paints the positioned glyphs as React SVG. `render.php` paints them as embedded, selectable PDF text using mPDF and imports passive PDF artwork using FPDI. Both use the same pinned fonts. System font fallback is outside this probe's repertoire.
- `run_english.py` compares content, line breaks, actual SVG glyph positions via the browser DOM, and PDF glyph origins, fonts and effective sizes via PyMuPDF. It checks positive and negative fits and compares overlay ink in representative screenshots with 300 dpi rasterizations of the generated PDFs. It also tests an RTL host with an LTR certificate at a smaller viewport and zoom. Geometric measurements are in millimetres or points.
- `fixtures.py` produces the English test configurations and synthetic visual assets. The supplied PDF contains a Content Credentials attachment and is tested as a rejection. A passive page-only derivative is used for positive comparisons. This is an offline synthetic-fixture preparation step.
- `browser.jsx`, `browser.html`, and `run.py` reproduce the independent Chromium and mPDF layout that the positioned version replaces in the experiment. Their case selection still permits reproducing the superseded mixed-language investigation.
- `background.py` measures artwork placement. `combine.php` tests compatibility with three passive synthetic inputs, including a generated output. Neither is an application workflow.

The new approach still uses mPDF and FPDI. Chromium and Python are experiment tools. Its English text policy for stored personal, program and department names remains undecided. The test repertoire includes accented Latin letters and common punctuation. It does not establish production validation or authorize translation or transliteration. The official HR template, an actual printer, other browsers, and Windows/Laragon still need deployment validation.
