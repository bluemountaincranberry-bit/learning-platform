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
