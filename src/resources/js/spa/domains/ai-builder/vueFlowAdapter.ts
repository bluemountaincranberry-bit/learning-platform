import type { Edge, Node } from '@vue-flow/core';
import type { GraphEdgeInstance, GraphNodeContractSummary, GraphNodeInstance, NodePaletteEntry } from './types';

/**
 * Thin translation between our own domain shape (GraphNodeInstance/
 * GraphEdgeInstance — exactly what the backend stores/accepts) and Vue
 * Flow's node/edge shape. Vue Flow's types never leak past this file —
 * the rest of the app only ever deals with the domain shape, same
 * separation n8n's own Canvas.vue keeps from its workflow domain model.
 */
export interface GraphCanvasNodeData {
    stepKey: string;
    nodeType: string;
    label: string;
    description: string;
    promptKey: string | null;
    contract: GraphNodeContractSummary | null;
}

export function domainNodeToFlowNode(instance: GraphNodeInstance, palette: NodePaletteEntry[]): Node<GraphCanvasNodeData> {
    const entry = palette.find((p) => p.node_key === instance.node);

    return {
        id: instance.key,
        type: 'graphStep',
        position: { x: instance.x ?? 0, y: instance.y ?? 0 },
        data: {
            stepKey: instance.key,
            nodeType: instance.node,
            label: entry?.label ?? instance.node,
            description: entry?.description ?? '',
            promptKey: entry?.prompt_key ?? null,
            contract: entry?.contract ?? null,
        },
    };
}

export function flowNodeToDomainNode(node: Node<GraphCanvasNodeData>): GraphNodeInstance {
    return {
        key: node.id,
        node: node.data!.nodeType,
        x: node.position.x,
        y: node.position.y,
    };
}

export function domainEdgeToFlowEdge(edge: GraphEdgeInstance): Edge {
    return {
        id: `${edge.from}->${edge.to}`,
        source: edge.from,
        target: edge.to,
        label: edge.label ?? undefined,
    };
}

export function flowEdgeToDomainEdge(edge: Edge): GraphEdgeInstance {
    return {
        from: edge.source,
        to: edge.target,
        label: typeof edge.label === 'string' ? edge.label : null,
    };
}
