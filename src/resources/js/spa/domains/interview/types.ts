export interface InterviewTopic { id: number; name: string; parentId: number | null; sortOrder: number; }
export interface InterviewAnswer { id: number; en: string | null; ru: string | null; revisions: { id: number; textEn: string | null; textRu: string | null; createdAt: string }[]; }
export interface InterviewQuestion {
    id: number; promptEn: string; promptRu: string | null;
    preparationState: 'unpracticed' | 'needs_practice' | 'confident';
    topic: InterviewTopic | null; tags: string[]; answers: Partial<Record<'short' | 'full', InterviewAnswer>>;
}
export interface InterviewProfile {
    id?: number; careerGoal: string | null; skills: string[] | null; experienceLevel: string | null;
    projects: string[] | null; experienceStories: string[] | null;
    milestones: { id?: number; title: string; targetDate: string | null }[];
}
export interface InterviewPracticeMessage { id: number; role: 'user' | 'assistant'; content: string; }
export interface InterviewPracticeSession {
    id: number; conversationId: number; mode: 'coached' | 'mock'; status: 'active' | 'completed';
    questionCount: number; focus: string | null; questions: InterviewQuestion[]; messages: InterviewPracticeMessage[];
}
export interface InterviewPracticeSummary { id: number; mode: 'coached' | 'mock'; status: 'active' | 'completed'; updatedAt: string; }
export type InterviewDraft =
    | { id: number; kind: 'question'; promptEn: string; promptRu: string | null; topicId: number | null; tags: string[] }
    | { id: number; kind: 'profile'; changes: { label: string; value: string }[] }
    | { id: number; kind: 'answer'; questionPromptEn: string; questionPromptRu: string | null; variant: 'short' | 'full'; textEn: string | null; textRu: string | null }
    | { id: number; kind: 'vocabulary'; lemma: string; language: 'en' }
    | { id: number; kind: 'observation'; questionPromptEn: string; questionPromptRu: string | null; preparationState: 'needs_practice' | 'confident'; evidence: string; reason: string };
