// ============================================================
// Configuration graphique centralisée
// ============================================================
const graphConfig = {
    base: {
        rankdir: "BT",
        bgcolor: "#333333",
        splines: "ortho",
        node: { shape: "box", style: "filled", color: "#87CEFA" }, // lightblue
        edge: { color: "#d4d4d4ff", fontsize: 10 }
    },
    colors: {
        material: "#6CB0FF",   // bleu
        root: "#6CC06F",       // vert
        selected: "#C06CBF",   // violet
        input: "#ffbe44ff",      // lightblue - pour les arêtes VERS les réactions
        output: "#4fd343ff",     // green - pour les arêtes DEPUIS les réactions
        indirect_input: "#dd8f00ff",   // orange
        indirect_output: "#108605ff",   // orange
        reaction: "#F9F9A9",   // jaune pâle
        start: "#F08080",      // lightcoral
        tag: "#FFA500"         // orange
    }
};

// ============================================================
// Fonction utilitaire
// ============================================================
function getGraphBaseDot(config = graphConfig.base) {
    return `
        digraph {
            rankdir="${config.rankdir}";
            bgcolor="${config.bgcolor}";
            splines=${config.splines};
            node [shape=${config.node.shape}, style=${config.node.style}, color="${config.node.color}", onclick="nodeClick(this.innerHTML)"];
            edge [fontsize=${config.edge.fontsize}, color="${config.edge.color}"];
    `;
}

function createNode(name, {
    label = null,
    shape = "box",
    fillcolor = graphConfig.colors.material,
    fontcolor = "#000000",
    style = "filled",
    url = "javascript:void(0);",
    tooltip = null
} = {}) {
    let attrs = [
        `label="${label || name}"`,
        `fillcolor="${fillcolor}"`,
        `fontcolor="${fontcolor}"`,
        `shape=${shape}`,
        `style=${style}`,
        `URL="${url}"`
    ];
    if (tooltip) attrs.push(`tooltip="${tooltip}"`);
    return `  "${name}" [${attrs.join(", ")}];`;
}

function createEdge(from, to, {
    color = graphConfig.base.edge.color,
    dir = "forward",
    style = "solid",
    label = null,
    constraint = true
} = {}) {
    let attrs = [
        `color="${color}"`,
        `style=${style}`,
        `dir=${dir}`
    ];
    if (label) attrs.push(`label="${label}"`);
    if (!constraint) attrs.push(`constraint=false`);
    return `  "${from}" -> "${to}" [${attrs.join(", ")}];`;
}

// ============================================================
// Fonction principale pour générer les graphiques de réactions et de hiérarchie
// ============================================================

function generateReactionsGraph(materialName, showIndirect = true) {
    if (!materialName) return 'digraph { node [shape=box]; "Sélectionnez un matériau"; }';

    const reactions = getReactionsForMaterial(materialsData, hierarchy, materialName);
    const formattedReactions = formatReactions(reactions);

    let dot = getGraphBaseDot();
    dot += createNode(materialName, { fillcolor: graphConfig.colors.start });

    const addedNodes = new Set([materialName]);

    formattedReactions.forEach((reaction, index) => {
        const isIndirect = reaction.input.some(c => c.startsWith('[')) || 
                           reaction.output.some(c => c.startsWith('['));
        if (!showIndirect && isIndirect) return;

        // Inputs - arêtes qui vont VERS la réaction
        reaction.input.forEach(input => {
            if (!addedNodes.has(input)) {
                dot += createNode(input);
                addedNodes.add(input);
            }
            dot += createEdge(input, `reaction_${index}`, {
                dir: "none",
                color: isIndirect ? graphConfig.colors.indirect_input : graphConfig.colors.input
            });
        });

        // Outputs - arêtes qui viennent DEPUIS la réaction
        reaction.output.forEach((output, i) => {
            if (!addedNodes.has(output)) {
                dot += createNode(output);
                addedNodes.add(output);
            }
            dot += createEdge(`reaction_${index}`, output, {
                color: isIndirect ? graphConfig.colors.indirect_output : graphConfig.colors.output,
                label: i === 0 ? reaction.probability : null
            });
        });

        // Node reaction
        const tooltip = `${reaction.input.join(' + ')} → ${reaction.output.join(' + ')} (P: ${reaction.probability})`;
        dot += createNode(`reaction_${index}`, {
            label: "Réaction",
            fillcolor: graphConfig.colors.reaction,
            fontcolor: "#333333",
            tooltip
        });
    });

    dot += "}";
    return dot;
}


function generateMaterialHierarchyGraph(materialName) {
    if (!materialName) return 'digraph { node [shape=box]; "Sélectionnez un matériau"; }';
    
    const genealogy = getMaterialGenealogy(hierarchy, materialName);
    let dot = getGraphBaseDot();
    const addedNodes = new Set();

    genealogy.forEach((material, index) => {
        let color = graphConfig.colors.material;
        if (material === materialName) color = graphConfig.colors.selected;
        else if (index === 0) color = graphConfig.colors.root;

        if (!addedNodes.has(material)) {
            dot += createNode(material, { fillcolor: color });
            addedNodes.add(material);
        }
    });

    // Relations hiérarchiques
    for (let i = 0; i < genealogy.length - 1; i++) {
        dot += createEdge(genealogy[i + 1], genealogy[i]);
    }

    // Tags liés
    const addedTags = new Set();
    genealogy.forEach(material => {
        const tags = getMaterialTags(materialsData, material);
        tags.forEach(tag => {
            if (!addedTags.has(tag)) {
                dot += createNode(tag, { 
                    fillcolor: graphConfig.colors.tag,
                    color: "#000000" 
                });
                addedTags.add(tag);
            }
            dot += createEdge(material, tag, { 
                style: "dashed", 
                dir: "none", 
                color: graphConfig.colors.tag, 
                constraint: false 
            });
        });
    });

    dot += "}";
    return dot;
}


function generateTagHierarchyGraph(tag) {
    if (!tag) return 'digraph { node [shape=box]; "Sélectionnez un tag"; }';
    
    const materialsWithTag = [];
    const materials = materialsData.querySelectorAll('CellData, CellDataChild');

    materials.forEach(material => {
        const name = material.getAttribute('name');
        const tagsAttr = material.getAttribute('tags');
        if (tagsAttr && tagsAttr.includes(tag)) {
            materialsWithTag.push(name);
        }
    });

    if (materialsWithTag.length === 0) {
        return 'digraph { node [shape=box]; "Aucun matériau avec ce tag"; }';
    }

    let dot = getGraphBaseDot();
    dot += createNode(tag, { fillcolor: graphConfig.colors.tag });

    const addedNodes = new Set();

    materialsWithTag.forEach(material => {
        const genealogy = getMaterialGenealogy(hierarchy, material);

        genealogy.forEach((mat, index) => {
            let color = graphConfig.colors.material;
            if (mat === material) color = graphConfig.colors.selected;
            else if (index === 0) color = graphConfig.colors.root;

            if (!addedNodes.has(mat)) {
                dot += createNode(mat, { fillcolor: color });
                addedNodes.add(mat);
            }
        });

        for (let i = 0; i < genealogy.length - 1; i++) {
            dot += createEdge(genealogy[i + 1], genealogy[i]);
        }

        dot += createEdge(material, tag, { 
            style: "dashed", 
            dir: "none", 
            color: graphConfig.colors.tag, 
            constraint: false 
        });
    });

    dot += "}";
    return dot;
}


function generateTagGraph(tag, showIndirect = true) {
    if (!tag) return 'digraph { node [shape=box]; "Sélectionnez un tag"; }';
    
    const reactions = materialsData.querySelectorAll('Reaction');
    const relevantReactions = [];

    reactions.forEach(reaction => {
        const inputCells = [
            reaction.getAttribute('input_cell1'),
            reaction.getAttribute('input_cell2'),
            reaction.getAttribute('input_cell3')
        ].filter(Boolean);

        const outputCells = [
            reaction.getAttribute('output_cell1'),
            reaction.getAttribute('output_cell2'),
            reaction.getAttribute('output_cell3')
        ].filter(Boolean);

        const isInputRelated = inputCells.some(cell => cell === tag);
        const isOutputRelated = outputCells.some(cell => cell === tag);

        if (isInputRelated || isOutputRelated) {
            relevantReactions.push({
                reaction: reaction, // Add the reaction element here
                isInput: isInputRelated,
                isOutput: isOutputRelated,
                isDirect: true // Tag reactions are always direct
            });
        }
    });

    const formattedReactions = formatReactions(relevantReactions);

    let dot = getGraphBaseDot({ ...graphConfig.base, rankdir: "LR" }); // gauche→droite pour tags
    dot += createNode(tag, { fillcolor: graphConfig.colors.tag });

    const addedNodes = new Set([tag]);

    formattedReactions.forEach((reaction, index) => {
        const isIndirect = reaction.input.some(c => c.startsWith('[')) || 
                           reaction.output.some(c => c.startsWith('['));
        if (!showIndirect && isIndirect) return;

        // Inputs - arêtes qui vont VERS la réaction
        reaction.input.forEach(input => {
            if (!addedNodes.has(input)) {
                dot += createNode(input);
                addedNodes.add(input);
            }
            // Si l'input est le tag sélectionné, on utilise la couleur tag, sinon input
            const color = input === tag ? graphConfig.colors.tag : 
                         (isIndirect ? graphConfig.colors.indirect_input : graphConfig.colors.input);
            dot += createEdge(input, `reaction_${index}`, { dir: "none", color });
        });

        // Outputs - arêtes qui viennent DEPUIS la réaction
        reaction.output.forEach((output, i) => {
            if (!addedNodes.has(output)) {
                dot += createNode(output);
                addedNodes.add(output);
            }
            // Si l'output est le tag sélectionné, on utilise la couleur tag, sinon output
            const color = output === tag ? graphConfig.colors.tag : 
                         (isIndirect ? graphConfig.colors.indirect_output : graphConfig.colors.output);
            dot += createEdge(`reaction_${index}`, output, {
                color,
                label: i === 0 ? reaction.probability : null
            });
        });

        // Nœud réaction
        const tooltip = `${reaction.input.join(' + ')} → ${reaction.output.join(' + ')} (P: ${reaction.probability})`;
        dot += createNode(`reaction_${index}`, {
            label: "Réaction",
            fillcolor: graphConfig.colors.reaction,
            fontcolor: "#333333",
            tooltip
        });
    });

    dot += "}";
    return dot;
}