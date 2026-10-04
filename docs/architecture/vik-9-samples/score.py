# VIK-9 scorer. Expects method outputs in pdft_out/, basic_out/, tess_out/, llm_out/ and
# real/ references (local-only, derived from src/tests/Fixtures/pdf) next to samples/.
# CER: whitespace-insensitive; words: order-independent recall. See ../lesson-photo-pdf-to-text.md.
import json, re, unicodedata
from collections import Counter
from pathlib import Path
import jiwer

gt = json.loads(Path("samples/ground-truth.json").read_text())
REF = {"s1": gt["s1-text.pdf"], "s2": gt["s2-scan.pdf"], "s3": gt["s3-photo.jpg"], "s4": gt["s4-board.jpg"],
       "s5": gt["s5-iam-handwriting.png"], "s6": Path("real/s6-ref-full.txt").read_text(), "s7": Path("real/s6-ref.txt").read_text()}

def norm(t):
    t = unicodedata.normalize("NFKC", t)
    for a, b in [("’", "'"), ("‘", "'"), ("“", '"'), ("”", '"'), ("–", "-"), ("—", "-"), ("ʼ", "'")]:
        t = t.replace(a, b)
    t = re.sub(r'\s+([,.;:)?!"])', r"\1", t)  # IAM tokenisation spaces before punctuation
    return t

def cer(ref, hyp):
    r = re.sub(r"\s+", "", norm(ref)); h = re.sub(r"\s+", "", norm(hyp))
    if not h:
        return 1.0
    return jiwer.cer(r, h)

def words(t):
    return Counter(w for w in re.findall(r"[\w'ˈː]+", norm(t).lower()))

def word_acc(ref, hyp):
    r, h = words(ref), words(hyp)
    return sum((r & h).values()) / sum(r.values())

def read(p):
    p = Path(p)
    if not p.exists():
        return ""
    t = p.read_text(errors="replace")
    return "" if t.startswith("ERROR:") else t

runs = {}
for s, f in [("s1", "s1-text"), ("s2", "s2-scan"), ("s6", "s6-real-text"), ("s7", "s7-real-scan")]:
    runs[(s, "pdftotext")] = read(f"pdft_out/{f}.txt"); runs[(s, "basic")] = read(f"basic_out/{f}.txt")
for s in ["s2", "s3", "s4", "s5", "s7"]:
    runs[(s, "tesseract")] = read(f"tess_out/{s}.txt")
for p in Path("llm_out").glob("*.txt"):
    s, m = p.name.split(".", 1); runs[(s, m[:-4])] = read(p)

rows = []
for (s, m), hyp in sorted(runs.items()):
    rows.append({"sample": s, "method": m, "cer": round(min(cer(REF[s], hyp), 1.0) * 100, 1),
                 "word_acc": round(word_acc(REF[s], hyp) * 100, 1)})
    print(f"{s:3} {m:14} CER {rows[-1]['cer']:6}%  words {rows[-1]['word_acc']:6}%")
Path("scores.json").write_text(json.dumps(rows, indent=1))
