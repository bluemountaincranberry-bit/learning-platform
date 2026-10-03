# ADR-007: Modular monolith as the rewrite target

## Status

Accepted for the pre-production rewrite.

## Decision

Keep one Laravel application with explicit business modules. Each capability
has one owner and communicates through direct application contracts, jobs, or
business events according to the complexity of the interaction.

## Rationale

The project needs clear boundaries and future scalability, but it does not yet
have measured operational pressure that justifies distributed services. A
modular monolith keeps transactions, local development, deployment, and
debugging simple while preserving a path to extract a module later.

## Consequences

- Models, jobs, events, policies, and resources must have an owning module.
- Cross-module imports are explicit and reviewed.
- Extraction to a service is considered only after measured need and a stable
  contract exist.
