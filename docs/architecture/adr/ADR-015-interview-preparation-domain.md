# ADR-015: Interview Preparation owns interview state

Interview goals, topics, tags, questions, answer variants and revisions,
experience profile, preparation state, and practice metadata belong to a
dedicated Interview module. The AI module owns model execution, tools and
conversation messages; its Interview Agent reads through explicit contracts
and submits drafts for learner confirmation, never writing confirmed Interview,
Learning, SRS, or Content state directly. Interview practice metadata links to
the AI conversation instead of copying its transcript or voice records, so the
existing ownership and recording-retention rules remain authoritative.
