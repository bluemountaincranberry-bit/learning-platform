# ADR-009: Contract-first and integration-first rewrite testing

## Status

Accepted for the pre-production rewrite.

## Decision

Each rewrite slice starts with acceptance criteria and executable contract
checks where practical. Critical flows receive integration tests before the
implementation is considered complete; unit tests cover isolated invariants
and calculations.

## Rationale

The rewrite allows API and schema breaks, so tests must protect intended
product behavior rather than legacy implementation details. Integration-first
matches the existing project testing policy and catches boundary regressions.

## Consequences

- A green build alone does not close a rewrite task.
- Provider adapters need contract tests.
- Content, learning, SRS and AI interactions need success and failure-path
  integration tests.
