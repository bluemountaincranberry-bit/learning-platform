# ADR 001: Modular monolith boundaries

## Decision

The application remains a Laravel modular monolith. `User`, `Content`,
`Learning`, `Srs`, `Ai`, `Admin`, and `Infrastructure` own their business
state and application actions. Cross-module calls use explicit contracts;
events are reserved for committed business facts and queues for heavy work.

## Consequences

Eloquent models remain a valid persistence implementation inside a module, but
are not event payloads or public frontend contracts. The final migration has
no compatibility classes or PHP boundary baseline. The strict checker rejects
private imports and module cycles; persisted morph strings are compatibility
data and resolve directly to canonical models.
