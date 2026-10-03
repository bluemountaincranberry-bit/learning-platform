export interface GraphNodeContractSummary {
    reads: string[];
    writes: string[];
    side_effect: 'read_only' | 'draft_only' | 'publish';
    execution: 'sync' | 'async_fanout';
    can_pause: boolean;
    failure_policy: 'fail' | 'best_effort';
    queues_jobs: boolean;
}

export interface NodePaletteEntry {
    node_key: string;
    label: string;
    description: string;
    prompt_key: string | null;
    contract: GraphNodeContractSummary | null;
}

export interface GraphNodeInstance {
    key: string;
    node: string;
    x?: number;
    y?: number;
}

export interface GraphEdgeInstance {
    from: string;
    to: string;
    label?: string | null;
}

export interface GraphDefinitionVersion {
    id: number;
    version: number;
    nodes: GraphNodeInstance[];
    edges: GraphEdgeInstance[];
    created_at: string | null;
}

export interface GraphDefinitionSummary {
    key: string;
    name: string;
    description: string | null;
    active_version_id: number | null;
}

export interface GraphDefinitionDetail extends GraphDefinitionSummary {
    versions: GraphDefinitionVersion[];
}

export interface PromptTemplateVersion {
    id: number;
    version: number;
    system_template: string;
    user_template: string;
    model: string | null;
    created_at: string | null;
}

export interface PromptTemplateSummary {
    key: string;
    name: string;
    description: string | null;
    active_version_id: number | null;
}

export interface PromptTemplateDetail extends PromptTemplateSummary {
    versions: PromptTemplateVersion[];
    kind: 'agent' | 'prompt';
    code_default_system: string | null;
    tools: AgentToolSummary[];
}

export interface PromptChatMessage {
    role: 'user' | 'assistant';
    content: string;
}

export interface PromptChatReply {
    reply: string;
    proposed_system_template: string | null;
    proposed_user_template: string | null;
}

export interface TestRunResult {
    rendered_system: string;
    rendered_user: string;
    response: string;
}

export interface GraphTestRunResult {
    status: 'completed' | 'paused' | 'failed';
    current_node: string | null;
    pause_reason: string | null;
    failure_reason: string | null;
    lexeme_candidates: Array<Record<string, unknown>>;
    grammar_candidates: Array<Record<string, unknown>>;
}

export interface AgentToolSummary {
    name: string;
    description: string;
    side_effect: 'read_only' | 'draft_only' | 'publish';
}

export interface PromptCatalogEntry {
    key: string;
    label: string;
    description: string;
    kind: 'agent' | 'prompt';
    has_override: boolean;
    code_default_preview: string | null;
    tools: AgentToolSummary[];
}

export interface PromptCatalogFlow {
    flow: string;
    label: string;
    entries: PromptCatalogEntry[];
}

export interface AgentSummary {
    agent_type: string;
    service_class: string;
    system_prompt: string;
    max_iterations: number;
    allowed_side_effects: string[];
    tools: string[];
    has_prompt_override: boolean;
}
