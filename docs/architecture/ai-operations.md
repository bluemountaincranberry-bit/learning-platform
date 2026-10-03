# AI operations

- Providers use bounded timeout/retry policies and return typed capability
  results or classified errors.
- Logs use `AiErrorMessage::safe()`; provider keys and raw authorization
  material are redacted and messages are truncated.
- AI calls never run inside a long database transaction. Failed attempts remain
  visible through execution traces and queued jobs stop after the configured
  retry budget.
- Product writes happen through owner actions (for example Content candidate
  application), with operation/event identifiers used for deduplication.
- Paid/live evaluations are separate from deterministic fixtures and require an
  explicit operational decision before execution.
- Provider-independent contracts are shared through `App\\Contracts\\Ai`;
  Content, Learning and SRS data reach AI only through public scalar/DTO read
  ports. The PHP boundary checker has no baseline exceptions.
