# ADR-015: Interview Preparation owns interview state

Interview goals, topics, tags, questions, answer variants and revisions,
experience profile, preparation state, and practice metadata belong to a
dedicated Interview module. The AI module owns model execution, tools and
conversation messages; its Interview Agent reads through explicit contracts
and submits drafts for learner confirmation, never writing confirmed Interview,
Learning, SRS, or Content state directly. Interview practice metadata links to
the AI conversation instead of copying its transcript or voice records, so the
existing ownership and recording-retention rules remain authoritative.

Interview creates and resumes conversations through the public AI conversation gateway; only AI writes conversation messages and dispatches agent turns. The Interview Agent reads its current session's questions and confirmed profile through an owner-scoped Interview context reader. It may create owner-scoped question or profile proposals through the Interview draft writer; the SPA exposes review, confirm and reject actions. Confirmation is the only path from a proposal into confirmed Interview state. Draft tools are explicitly marked `draft_only` and constrained by the agent blueprint.
