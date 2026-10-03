# VIK-9: lesson photos and PDFs to editable text

Research date: 2026-10-03. Decision input for VIK-18. Measurements were run in this repo on the samples in [`vik-9-samples/`](vik-9-samples/). Each number is marked **M** (measured here) or **C** (computed from official pricing and token formulas, not run).

## Question

What is the best way to turn group-lesson material into editable lesson text? The material is photos of boards and notebooks (handwritten and printed), scanned PDFs and text PDFs. Lessons are bilingual: English terms with Russian glosses, plus IPA.

Starting point: VIK-42 replaced the hand-rolled `BasicPdfTextExtractor` with poppler `pdftotext` ([PopplerPdfTextExtractor](../../src/app/Modules/Content/Infrastructure/Pdf/PopplerPdfTextExtractor.php)). Scans still fail with "This PDF is a scan…", and lessons accept PDFs only ([SendLessonMessageRequest](../../src/app/Http/Requests/Api/SendLessonMessageRequest.php)).

## Options

| Option | Input it can read | Status in the app |
|---|---|---|
| Old hand-rolled extractor | Text PDF (in theory) | Removed by VIK-42 |
| poppler `pdftotext` | Text PDF only | In the app image, live |
| Tesseract 5 (`eng+rus`) | Images and rasterised scans | Not installed. Would add Debian packages to the image |
| OpenAI vision (`gpt-4o-mini`, `gpt-4.1-mini`, `gpt-5.4-mini`) | Images; scans after rasterising | OpenAI is the configured provider (`AI_PROVIDER=openai`) |
| Claude vision (Haiku 4.5, Sonnet 5.5, Opus 5.5) | Images and PDFs | No Anthropic adapter or key: a **new paid service** |
| Google Cloud Vision, AWS Textract, Azure Document Intelligence Read | Images and PDFs | No account or key: a **new paid service** |

## Samples and method

There were no lesson-photo fixtures in the repo. The only real lesson file is [`tests/Fixtures/pdf/wordlist-unit-1d.pdf`](../../src/tests/Fixtures/pdf/wordlist-unit-1d.pdf), and it was processed **locally only**. Only synthetic or public samples were sent to OpenAI.

| # | Sample | Source | Ground truth |
|---|---|---|---|
| S1 | Text PDF, two-column EN/RU word list with IPA | Synthetic ([generator](vik-9-samples/generate_samples.py)) | Source text |
| S2 | S1 as a noisy 150-dpi scan (0.8° skew, JPEG q55), image-only PDF | Synthetic | Same as S1 |
| S3 | Phone-style photo of a printed grammar handout (perspective, uneven light, blur) | Synthetic | Source text |
| S4 | Whiteboard in a handwriting font, EN + RU, three marker colours, glare | Synthetic (Caveat font, OFL) | Source text |
| S5 | Real English handwriting: 6 stacked lines | IAM via [Teklia/IAM-line](https://huggingface.co/datasets/Teklia/IAM-line) test split. Not committed because of the IAM licence | IAM transcription |
| S6 | Real lesson PDF (3 pages, CID fonts) | Repo fixture, local only | `pdftotext` raw output, spot-checked by eye |
| S7 | Page 1 of S6 degraded like S2 | Derived from S6, local only | Text layer of S6 page 1 |

Metrics ([score.py](vik-9-samples/score.py); raw numbers in [measurements.json](vik-9-samples/measurements.json)):

- **CER**: character error rate after removing whitespace and normalising quotes and dashes. It is sensitive to reading order, so two-column pages score worse even when every word is right.
- **Words**: order-independent recall of ground-truth words, case-insensitive. This is the main metric, because what lands in lesson notes is the words.

Each method ran once per sample:

- `pdftotext -layout`: the production command, run in the `blue-app` image (poppler 22.12).
- Old extractor: the VIK-42-removed class, run in the same image.
- Tesseract 5.3.4 with `-l eng+rus` in a throwaway Ubuntu container, one thread. PDFs were rasterised at 300 dpi as Tesseract recommends ([ImproveQuality](https://tesseract-ocr.github.io/tessdoc/ImproveQuality.html)).
- OpenAI Chat Completions with `detail: high`, a "transcribe exactly, do not translate" prompt, and temperature 0 (or `reasoning_effort: low` for gpt-5.4-mini).

Latency is wall-clock from this machine, including network for the APIs.

## Results

CER % / words % · latency · cost per page. All are **M** except the Claude rows.

| Sample | pdftotext | Old extractor | Tesseract eng+rus | gpt-4o-mini | **gpt-4.1-mini** | gpt-5.4-mini |
|---|---|---|---|---|---|---|
| S1 text PDF | **0 / 100** · 15 ms · $0 | 100 / 10 (binary font bytes) | n/a | n/a | n/a | n/a |
| S2 scan PDF | no text (error) | no text (error) | 2.2 / 91.6 · 1.5 s · $0 | 1.1 / 100 · 5.1 s · $0.0057 | **0.0 / 100 · 2.9 s · $0.0017** | 0.4 / 99.2 · 3.0 s · $0.0034 |
| S3 photo, printed | n/a | n/a | 11.8 / 96.5 · 0.4 s | 0 / 100 · 2.8 s · $0.0039 | **0 / 100 · 1.7 s · $0.0014** | 0 / 100 · 1.7 s · $0.0024 |
| S4 board, EN+RU "handwriting" | n/a | n/a | 12.3 / 61.3 · 0.4 s | 0 / 100 · 2.4 s · $0.0056 | **0 / 100 · 1.5 s · $0.0012** | 0.6 / 100 · 1.8 s · $0.0021 |
| S5 real handwriting (IAM) | n/a | n/a | 47.1 / 24.1 · 0.4 s | 3.4 / 93.1 · 2.5 s · $0.0031 | **1.4 / 93.1 · 1.5 s · $0.0006** | 1.0 / 94.8 · 3.1 s · $0.0020 |
| S6 real lesson PDF | **— / 100** · 31 ms (3 pages) | 99.5 / 0 ("Adobe UCS" ×N) | n/a | not sent | not sent | not sent |
| S7 real page, scanned | no text | no text | 22.5 / 95.3 · 3.3 s | not sent | not sent | not sent |

Notes:

- S5: the remaining LLM "errors" are mostly how IAM writes its ground truth (`I T V` for "ITV"; `Love` where the image shows "love"). The one real miss is gpt-4.1-mini reading "Fay" as "Gay".
- Tesseract's S2 and S7 word losses are mostly Latin and Cyrillic look-alikes. For example it returns "It took me **а** month" with a Cyrillic а. The text looks right but will not match words in search or lexeme lookup. Its S3 and S7 CER is high because of line and column order.
- gpt-4o-mini, the app's current default model, bills images at 2,833 + 5,667 tokens per tile ([OpenAI vision docs](https://developers.openai.com/api/docs/guides/images-vision)). Measured: 36,894 input tokens for one page, against 3,533 on gpt-4.1-mini. So the cheapest text model is **2.5–5× more expensive per image** and the slowest. It also wrapped its output in ``` fences.

Mean of the four API samples (S2–S5), **M**:

| Model | Mean latency (max) | Mean cost per page |
|---|---|---|
| gpt-4o-mini | 3.2 s (5.1 s) | $0.0046 |
| gpt-4.1-mini | 1.9 s (2.9 s) | $0.0012 |
| gpt-5.4-mini | 2.4 s (3.1 s) | $0.0025 |

The cost rows below are **C**, for the same four images. Image tokens use Anthropic's `⌈w/28⌉×⌈h/28⌉` rule: Claude 4.7 and later allow up to a 2576 px long edge and 4,784 tokens, and Haiku 4.5 allows 1568 px and 1,568 tokens ([Claude vision docs](https://platform.claude.com/docs/en/build-with-claude/vision)). Output tokens are taken from the gpt-4.1-mini runs, ×1.3 for the newer Claude tokenizer ([pricing](https://platform.claude.com/docs/en/about-claude/pricing)). Accuracy for these rows was **not measured**.

| Service | Cost per page (C) |
|---|---|
| Claude Haiku 4.5 ($1 / $5 per MTok) | $0.0020 |
| Claude Sonnet 5.5 ($2 / $10) | $0.0057 |
| Claude Opus 5.5 ($4 / $20) | $0.0115 |
| Google Vision document text detection | $0.0015 (first 1,000 units/month free) ([pricing](https://cloud.google.com/vision/pricing)) |
| AWS Textract DetectDocumentText | $0.0015 ([pricing](https://aws.amazon.com/textract/pricing/)) |
| Azure Document Intelligence Read (S0) | $0.0015 (500 pages/month free on F0) ([pricing page](https://azure.microsoft.com/en-us/pricing/details/document-intelligence/), price from the [Azure retail prices API](https://prices.azure.com/api/retail/prices)) |

OpenAI list prices used for the measured costs ([pricing](https://developers.openai.com/api/docs/pricing)):

| Model | Input per MTok | Output per MTok |
|---|---|---|
| gpt-4o-mini | $0.15 | $0.60 |
| gpt-4.1-mini | $0.40 | $1.60 |
| gpt-5.4-mini | $0.75 | $4.50 |

Batch is −50% on all of them.

## Privacy

| Provider | Training on API data | Retention |
|---|---|---|
| OpenAI API | Not used by default ([your data](https://developers.openai.com/api/docs/guides/your-data)) | Abuse-monitoring logs up to 30 days. Chat Completions is eligible for Zero Data Retention. Responses API `store` keeps data ≥30 days unless `store: false` |
| Anthropic API | Not used by default for commercial products ([privacy center](https://privacy.claude.com/en/articles/7996868-is-my-data-used-for-model-training)). The vision FAQ says uploaded images are not used for training | Deleted within 30 days ([retention](https://privacy.claude.com/en/articles/7996866-how-long-do-you-store-my-organization-s-data)). ZDR by agreement |
| Google Cloud Vision | Not used to train ([data usage](https://docs.cloud.google.com/vision/docs/data-usage)) | Online requests are processed in memory and not persisted |
| AWS Textract | **May be used to improve Amazon AI unless you opt out** through an Organizations policy ([FAQ](https://aws.amazon.com/textract/faqs/)) | Stored for service improvement unless you opt out |
| Azure Document Intelligence | No training use stated | Input and results kept 24 h, deletable earlier ([data privacy](https://learn.microsoft.com/en-us/legal/cognitive-services/document-intelligence/data-privacy-security)) |
| Tesseract and pdftotext | Local | Nothing leaves the server |

Lesson photos can show classmates or a teacher's name. The OpenAI path sends nothing that the app does not already send today: lesson notes already go to the same provider. VIK-18 should use Chat Completions without storage and strip EXIF and GPS before upload.

## Recommendation

1. **Text PDFs: keep `pdftotext`** (VIK-42). It is exact (100% of words on S1 and S6), takes about 15–30 ms, costs $0 and runs without AI. The old extractor was useless on both text PDFs and should not come back.
2. **Photos, scans and handwriting: use a vision LLM through the already-configured OpenAI provider, defaulting to `gpt-4.1-mini`.** It was the best measured on every image sample (0–1.4% CER, 93–100% words, including Cyrillic and real handwriting). It is about $0.0012 per page and about 2 s per page. Keep the model configurable and use `gpt-5.4-mini` as the tested alternative: same accuracy at 2× the cost. It is newer, so it is the move when 4.1-mini is retired. **Do not use `gpt-4o-mini` for images**: it costs 4× more for the same result.
3. **Do not add Tesseract or cloud OCR now.**
   - Tesseract is free and fast, but loses 39–76% of words on handwriting-style boards and real handwriting. It also corrupts printed EN/RU text with look-alike letters.
   - Cloud OCR costs about the same per page as gpt-4.1-mini, adds a new vendor and key, gives no layout or reading-order cleanup, and Textract trains on inputs by default.
   - Claude is a fine second provider later through the ADR-008 adapter seam. It is not justified now: it is a new paid service and costs more per page.

**No new paid service is required.** OpenAI is already configured. Escalating to Vika is not needed.

### Cost estimate (C, from the measured per-page costs)

Assumption: one group learner, 2 lessons a week, about 5 pages or photos each, which is about 40 pages a month.

| Model | Per page | Per month |
|---|---|---|
| gpt-4.1-mini | $0.0012 mean, $0.0017 worst (full A4 scan) | about $0.05–0.07 |
| gpt-5.4-mini | $0.0025 | about $0.10 |

A future 100-learner production scale is about 4,000 pages a month: roughly $5–7 a month on gpt-4.1-mini, or half that with the Batch API if results do not need to be instant.

## ADR and principle check

- **ADR-008 (provider-independent AI)**: followed. A new capability contract is added, the vendor code lives in the `Ai` adapter, and nothing outside `Ai` builds an OpenAI client. There is one gap to close in VIK-18 rather than a conflict: [`config/ai.php` `pricing`](../../src/config/ai.php) assumes a single model (gpt-4o-mini). Vision calls on a second model need per-model pricing so that cost tracking stays truthful.
- **ADR-002 (tools before agents)**: followed. Text recognition is one deterministic call per page, not an agent. Today, PDF text reaches lesson notes only when the chat agent decides to call `extract_pdf_text` ([ExtractPdfTextTool](../../src/app/Modules/Ai/Application/Agent/Tools/ExtractPdfTextTool.php)).
- **Product principle "basic actions work without AI"**: the current upload endpoint returns 503 when the agent is disabled ([LessonController::storeMessage](../../src/app/Modules/Learning/Interfaces/Http/Controllers/LessonController.php)). That blocks even `pdftotext`, which needs no AI. VIK-18 should decouple them, as described below. This contradicts no ADR.

## VIK-18 implementation outline

1. **Ownership**
   - `Learning` owns the lesson and its attachments.
   - `Content` owns document text extraction. It already owns `PdfTextExtractorInterface`.
   - `Ai` owns the vision call.
2. **Contracts**
   - In `App\Contracts\Ai`: `ImageTextRecognitionCapability::recognize(ImageInput $image, ?string $languageHint): RecognizedText`. `RecognizedText` carries the text, model, token usage and latency. This follows the existing `*Capability` naming and VIK-7's shared contract boundary.
   - In `Content`: `LessonMaterialTextExtractor::extract(path, mime): ExtractedText`, which orchestrates the steps below.
3. **Adapter**
   - `OpenAiImageTextRecognizer` in `Ai/Infrastructure`, behind a new `AiVisionClient` interface that `OpenAiClient` implements. It uses Chat Completions with `detail: high`, a fixed "transcribe verbatim, keep languages, no commentary" prompt kept in the prompt registry, and strips code fences from the output.
   - Model comes from `AI_VISION_MODEL`, default `gpt-4.1-mini`.
   - Records spans, usage and per-model cost as ADR-008 requires.
4. **Pipeline** (Content)
   - Text PDF: `pdftotext`. If it returns empty text, rasterise each page with `pdftoppm -r 150 -jpeg`. `pdftoppm` is already in the image.
   - Each page image, or an uploaded JPEG/PNG/WebP: downscale to a long edge of 2048 px or less with GD, re-encode as JPEG q85 (which also drops EXIF and GPS), then recognise.
   - Join pages with page markers and wrap the text as untrusted data (`<tool_output>` style) before any prompt uses it.
5. **Sync vs queued**
   - **Queued**: an `ExtractLessonMaterialTextJob` per attachment on the AI queue (Horizon), with `tries=3`, backoff, and a 60 s timeout per page. Vision calls take about 2–3 s per page and 20+ s for multi-page scans.
   - The text-PDF path is fast enough to run inline, but use the same job for one flow.
   - On success, append to lesson notes through `LessonNotesWriterInterface` and record per-attachment status: `pending`, `extracted`, `failed`, `needs_ai`.
   - The chat agent's `extract_pdf_text` tool becomes a reader of the stored result, not the trigger.
6. **Fallback when AI is down or disabled**
   - Upload must not require the agent. Accept and store the file, run `pdftotext` with no AI, and mark images and scans `needs_ai` with a "Retry recognition" action.
   - The lesson stays readable and editable by hand.
   - The port leaves room for a local Tesseract adapter later, if offline OCR for printed scans becomes a real need. It is not proposed now (see results).
7. **Limits**
   - Keep `AI_AGENT_MAX_UPLOAD_KB` (10 MB per file).
   - New: `AI_VISION_MAX_PDF_PAGES` (for example 20), `AI_VISION_MAX_IMAGE_EDGE=2048`, and up to 10 files per upload.
   - Count pages against the existing daily rate limit.
   - Accept `image/jpeg`, `image/png`, `image/webp`. HEIC is not supported by GD in the image, so verify what the phone browser actually uploads.
   - Truncate stored text at `ai.analysis.max_transcript_chars` only when feeding a prompt, not when saving notes.
8. **Tests**
   - Fake `ImageTextRecognitionCapability` in feature tests.
   - Reuse `vik-9-samples/` as a small eval set: words ≥ 95% on S2–S4 as a regression gate for prompt or model changes, run manually or nightly, never in CI against the live API.

## Sources

- OpenAI: [pricing](https://developers.openai.com/api/docs/pricing), [image token rules](https://developers.openai.com/api/docs/guides/images-vision), [data controls](https://developers.openai.com/api/docs/guides/your-data)
- Anthropic: [pricing](https://platform.claude.com/docs/en/about-claude/pricing), [vision and image tokens](https://platform.claude.com/docs/en/build-with-claude/vision), [training](https://privacy.claude.com/en/articles/7996868-is-my-data-used-for-model-training), [retention](https://privacy.claude.com/en/articles/7996866-how-long-do-you-store-my-organization-s-data)
- Google: [Vision pricing](https://cloud.google.com/vision/pricing), [Vision data usage](https://docs.cloud.google.com/vision/docs/data-usage)
- AWS: [Textract pricing](https://aws.amazon.com/textract/pricing/), [Textract FAQ (data use and opt-out)](https://aws.amazon.com/textract/faqs/)
- Azure: [Document Intelligence pricing](https://azure.microsoft.com/en-us/pricing/details/document-intelligence/), [retail prices API](https://prices.azure.com/api/retail/prices), [data privacy](https://learn.microsoft.com/en-us/legal/cognitive-services/document-intelligence/data-privacy-security)
- Tesseract: [ImproveQuality (300 dpi, skew, noise)](https://tesseract-ocr.github.io/tessdoc/ImproveQuality.html)
- poppler: [pdftotext(1)](https://manpages.debian.org/bookworm/poppler-utils/pdftotext.1.en.html)
- Handwriting sample: [Teklia/IAM-line](https://huggingface.co/datasets/Teklia/IAM-line)
