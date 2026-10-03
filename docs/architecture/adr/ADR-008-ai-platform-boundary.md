# ADR-008: AI as a provider-independent platform

## Status

Accepted for the pre-production rewrite.

## Decision

AI product features are implemented as capabilities behind stable application
contracts. Vendor-specific clients live in provider adapters. Runtime concerns
such as agents, tools, graphs, prompts, retrieval, evaluation and tracing are
separate from learner/content domain state.

## Rationale

AI is already a large part of the product and will change faster than core
learning rules. Separating capabilities from providers allows model routing,
evaluation, cost controls and future providers without leaking vendor details
through the application.

## Consequences

- No product service directly constructs an OpenAI/Ollama/Azure client.
- Every AI call records model, latency, token usage, cost and outcome when
  supported by the provider.
- Tool permissions and side effects are explicit.
- AI never owns learning progress, SRS state, or content visibility.
