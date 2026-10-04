"""Generate synthetic VIK-9 OCR samples + ground truth.

Needs: pip install reportlab pillow numpy pyarrow; DejaVu fonts; in the cwd
Caveat.ttf (google/fonts ofl/caveat) and iam_test.parquet (Teklia/IAM-line
data/test.parquet). S5 (IAM) is generated but not committed (IAM licence).
See ../lesson-photo-pdf-to-text.md.
"""
import io, json, random, textwrap
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont
from reportlab.lib.pagesizes import A4
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas

OUT = Path("samples"); OUT.mkdir(exist_ok=True)
random.seed(9); np.random.seed(9)
SANS = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
SANS_B = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
CAVEAT = "Caveat.ttf"

# ---------- S1/S2: bilingual word list (two columns) ----------
WORDLIST = [
    ("to get used to smth", "привыкать к чему-либо", "It took me a month to get used to the new office."),
    ("to put off", "откладывать", "Don't put off the report until Friday."),
    ("a steep learning curve", "крутая кривая обучения", "The new software has a steep learning curve."),
    ("to come up with", "придумать, предложить", "She came up with a brilliant idea [aɪˈdɪə]."),
    ("to run out of", "заканчиваться (о запасах)", "We ran out of coffee before the meeting."),
    ("reliable", "надёжный", "He is the most reliable person on the team."),
    ("to look forward to", "с нетерпением ждать", "I'm looking forward to our trip to Edinburgh."),
    ("to be fed up with", "быть сытым по горло", "They were fed up with endless delays."),
]
TITLE = "Unit 3B  Work and Habits"

def s1_gt():
    lines = [TITLE]
    for i, (en, ru, ex) in enumerate(WORDLIST, 1):
        lines.append(f"{i} {en} {ru}")
        lines.append(ex)
    return "\n".join(lines)

def make_s1():
    pdfmetrics.registerFont(TTFont("DV", SANS)); pdfmetrics.registerFont(TTFont("DVB", SANS_B))
    c = canvas.Canvas(str(OUT / "s1-text.pdf"), pagesize=A4)
    w, h = A4; y = h - 70
    c.setFont("DVB", 16); c.drawString(60, y, TITLE); y -= 40
    for i, (en, ru, ex) in enumerate(WORDLIST, 1):
        c.setFont("DV", 11)
        c.drawString(60, y, f"{i}"); c.drawString(80, y, en); c.drawString(290, y, ru); y -= 16
        c.setFont("DV", 10); c.drawString(290, y, ex) if len(ex) < 50 else None
        if len(ex) >= 50:
            c.drawString(290, y, ex[:ex.rfind(' ', 0, 48)]); y -= 14
            c.drawString(290, y, ex[ex.rfind(' ', 0, 48) + 1:])
        y -= 26
    c.save()

def render_page_image(dpi=150):
    """Render S1 content as a raster page (simulating a scanner)."""
    scale = dpi / 72
    W, H = int(595 * scale), int(842 * scale)
    img = Image.new("L", (W, H), 255); d = ImageDraw.Draw(img)
    fb = ImageFont.truetype(SANS_B, int(16 * scale)); f11 = ImageFont.truetype(SANS, int(11 * scale)); f10 = ImageFont.truetype(SANS, int(10 * scale))
    y = 70
    d.text((60 * scale, y * scale), TITLE, font=fb, fill=0); y += 40
    for i, (en, ru, ex) in enumerate(WORDLIST, 1):
        d.text((60 * scale, y * scale), str(i), font=f11, fill=0)
        d.text((80 * scale, y * scale), en, font=f11, fill=0)
        d.text((290 * scale, y * scale), ru, font=f11, fill=0); y += 16
        if len(ex) >= 50:
            cut = ex.rfind(' ', 0, 48)
            d.text((290 * scale, y * scale), ex[:cut], font=f10, fill=0); y += 14
            d.text((290 * scale, y * scale), ex[cut + 1:], font=f10, fill=0)
        else:
            d.text((290 * scale, y * scale), ex, font=f10, fill=0)
        y += 26
    return img

def make_s2():
    img = render_page_image(150).rotate(0.8, expand=False, fillcolor=255, resample=Image.BICUBIC)
    a = np.asarray(img).astype(np.float32)
    a += np.random.normal(0, 18, a.shape)  # scanner noise
    a = np.clip(a * 0.92 + 12, 0, 255).astype(np.uint8)
    img = Image.fromarray(a).filter(ImageFilter.GaussianBlur(0.6))
    buf = io.BytesIO(); img.save(buf, "JPEG", quality=55); buf.seek(0)
    Image.open(buf).save(OUT / "s2-scan.pdf", "PDF", resolution=150)

# ---------- S3: photographed printed handout ----------
GRAMMAR = """Present Perfect vs Past Simple
We use the Present Perfect for experiences and for actions
with a result now: I have lost my keys, so I can't get in.
We use the Past Simple for finished actions at a definite time:
I lost my keys yesterday.
Signal words: already, yet, ever, never, just, since, for.
Exercise 1. Choose the correct form.
1. She (has visited / visited) Rome three times.
2. We (have moved / moved) to Berlin in 2019.
3. Have you ever (eaten / ate) sushi?"""

def perspective_coeffs(src, dst):
    m = []
    for (x, y), (u, v) in zip(dst, src):
        m.append([x, y, 1, 0, 0, 0, -u * x, -u * y]); m.append([0, 0, 0, x, y, 1, -v * x, -v * y])
    A = np.array(m, dtype=float); B = np.array(src).reshape(8)
    return np.linalg.solve(A, B).tolist()

def make_s3():
    W, H = 1240, 1754
    page = Image.new("RGB", (W, H), (250, 248, 240)); d = ImageDraw.Draw(page)
    ft = ImageFont.truetype(SANS_B, 40); f = ImageFont.truetype(SANS, 30)
    y = 120
    for k, line in enumerate(GRAMMAR.split("\n")):
        d.text((100, y), line, font=ft if k == 0 else f, fill=(25, 25, 25)); y += 70 if k == 0 else 52
    bg = Image.new("RGB", (1600, 2000), (120, 100, 80))
    bg.paste(page, (180, 120))
    # perspective tilt
    src = [(180, 120), (180 + W, 120), (180 + W, 120 + H), (180, 120 + H)]
    dst = [(230, 160), (1480, 110), (1540, 1900), (150, 1850)]
    img = bg.transform(bg.size, Image.PERSPECTIVE, perspective_coeffs(src, dst), Image.BICUBIC)
    a = np.asarray(img).astype(np.float32)
    gx = np.linspace(0.65, 1.05, a.shape[1])[None, :, None]; gy = np.linspace(1.0, 0.8, a.shape[0])[:, None, None]
    a = np.clip(a * gx * gy + np.random.normal(0, 6, a.shape), 0, 255).astype(np.uint8)
    img = Image.fromarray(a).filter(ImageFilter.GaussianBlur(1.1)).resize((1200, 1500))
    img.save(OUT / "s3-photo.jpg", "JPEG", quality=70)

# ---------- S4: whiteboard, handwriting-style font, EN + RU ----------
BOARD = """Phrasal verbs: TAKE
take up - начать заниматься (hobby)
take off - взлететь; снять одежду
take after smb - быть похожим на родственника
She takes after her mother.
Homework: p. 47 ex. 2, 3"""

def make_s4():
    W, H = 1600, 1000
    img = Image.new("RGB", (W, H), (236, 238, 235)); d = ImageDraw.Draw(img)
    colors = [(20, 40, 160), (25, 25, 25), (25, 25, 25), (25, 25, 25), (170, 20, 20), (20, 110, 40)]
    y = 70
    for k, line in enumerate(BOARD.split("\n")):
        x = 80 + random.randint(-10, 10)
        for word in line.split(" "):
            f = ImageFont.truetype(CAVEAT, random.randint(58, 66) if k else 76)
            d.text((x, y + random.randint(-4, 4)), word, font=f, fill=colors[k])
            x += int(d.textlength(word + " ", font=f))
        y += 140
    img = img.rotate(-1.5, fillcolor=(200, 200, 200), resample=Image.BICUBIC)
    a = np.asarray(img).astype(np.float32)
    yy, xx = np.mgrid[0:H, 0:W]
    glare = 70 * np.exp(-(((xx - 1150) / 300) ** 2 + ((yy - 300) / 200) ** 2))
    a = np.clip(a + glare[..., None] + np.random.normal(0, 7, a.shape), 0, 255).astype(np.uint8)
    Image.fromarray(a).filter(ImageFilter.GaussianBlur(1.0)).save(OUT / "s4-board.jpg", "JPEG", quality=70)

# ---------- S5: real handwriting, IAM test lines stacked as a notebook page ----------
def make_s5():
    import pyarrow.parquet as pq
    t = pq.read_table("iam_test.parquet").slice(0, 40).to_pylist()
    picked = [r for r in t if 30 <= len(r["text"]) <= 70][:6]
    lines = [Image.open(io.BytesIO(r["image"]["bytes"])).convert("L") for r in picked]
    W = max(i.width for i in lines) + 80; H = sum(i.height + 30 for i in lines) + 60
    page = Image.new("L", (W, H), 255); y = 30
    for im in lines:
        page.paste(im, (40, y)); y += im.height + 30
    page = page.resize((W // 2, H // 2))
    page.save(OUT / "s5-iam-handwriting.png", optimize=True)
    return "\n".join(r["text"] for r in picked)

make_s1(); make_s2(); make_s3(); make_s4(); s5 = make_s5()
gt = {
    "s1-text.pdf": s1_gt(), "s2-scan.pdf": s1_gt(), "s3-photo.jpg": GRAMMAR,
    "s4-board.jpg": BOARD, "s5-iam-handwriting.png": s5,
}
(OUT / "ground-truth.json").write_text(json.dumps(gt, ensure_ascii=False, indent=2))
print(s5)
