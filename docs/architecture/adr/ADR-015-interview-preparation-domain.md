# ADR-015: Interview Preparation owns interview state

Interview goals, topics, tags, questions, answer variants and revisions,
experience profile, preparation state, and practice metadata belong to a
dedicated Interview module. The AI module owns model execution, tools and
conversation messages; its Interview Agent reads through explicit contracts
and submits drafts for learner confirmation, never writing confirmed Interview,
Learning, SRS, or Content state directly. Interview practice metadata links to
the AI conversation instead of copying its transcript or voice records, so the
existing ownership and recording-retention rules remain authoritative.
Repeated coaching observations are a separate profile-owned record, backed by
at least two distinct completed session/question examples with exact
learner-message evidence; they remain pending drafts until explicitly confirmed.
Interview practice accepts the reviewed transcript and its recording through the
existing AI message boundary; playback and pinning use the shared voice-recording
endpoint, with the same 30-day expiry unless the learner pins that recording.

Interview creates and resumes conversations through the public AI conversation gateway; only AI writes conversation messages and dispatches agent turns. The Interview Agent reads its current session's questions and confirmed profile, plus bounded learned English vocabulary and owner-scoped evidence from recent completed sessions, through application contracts. It may create owner-scoped question, answer, profile, vocabulary, evidence-backed question-state, or repeated-pattern observation proposals through the Interview draft writer; the SPA exposes evidence preview, confirm and reject actions. Confirmation is the only path from a proposal into confirmed Interview or Learning state. Vocabulary confirmation delegates to the Learning-owned personal vocabulary writer. Draft tools are explicitly marked `draft_only` and constrained by the agent blueprint.
