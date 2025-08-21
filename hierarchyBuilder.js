function buildMaterialHierarchy(xmlDoc) {
    const hierarchy = {};
    const materials = xmlDoc.querySelectorAll('CellData, CellDataChild');
    
    materials.forEach(material => {
        const name = material.getAttribute('name');
        const parent = material.getAttribute('_parent');
        
        hierarchy[name] = {
            parent: parent || null,
            children: []
        };
    });
    
    // Lier les enfants aux parents
    for (const materialName in hierarchy) {
        const parentName = hierarchy[materialName].parent;
        if (parentName && hierarchy[parentName]) {
            hierarchy[parentName].children.push(materialName);
        }
    }
    
    return hierarchy;
}

function getAllDescendants(hierarchy, materialName, includeSelf = true) {
    const descendants = includeSelf ? [materialName] : [];
    
    function getChildren(name) {
        const children = hierarchy[name]?.children || [];
        for (const child of children) {
            descendants.push(child);
            getChildren(child);
        }
    }
    
    if (hierarchy[materialName]) {
        getChildren(materialName);
    }
    
    return [...new Set(descendants)]; // Éviter les doublons
}

function getReactionsForMaterial(xmlDoc, hierarchy, targetMaterial) {
    const reactions = xmlDoc.querySelectorAll('Reaction');
    const relevantReactions = [];
    
    // Récupérer tous les matériaux de la hiérarchie
    const allRelatedMaterials = getAllDescendants(hierarchy, targetMaterial, true);
    
    reactions.forEach(reaction => {
        // Vérifier les cellules d'entrée
        const inputCells = [
            reaction.getAttribute('input_cell1'),
            reaction.getAttribute('input_cell2'),
            reaction.getAttribute('input_cell3')
        ].filter(Boolean);
        
        // Vérifier les cellules de sortie
        const outputCells = [
            reaction.getAttribute('output_cell1'),
            reaction.getAttribute('output_cell2'),
            reaction.getAttribute('output_cell3')
        ].filter(Boolean);
        
        // Vérifier si le matériau cible ou ses descendants sont concernés
        const isInputRelated = inputCells.some(cell => 
            allRelatedMaterials.includes(cell) || 
            (cell.startsWith('[') && cell.endsWith(']') && 
             checkTagMatch(cell, targetMaterial, hierarchy, xmlDoc))
        );
        
        const isOutputRelated = outputCells.some(cell => 
            allRelatedMaterials.includes(cell) || 
            (cell.startsWith('[') && cell.endsWith(']') && 
             checkTagMatch(cell, targetMaterial, hierarchy, xmlDoc))
        );
        
        if (isInputRelated || isOutputRelated) {
            relevantReactions.push({
                reaction: reaction,
                isInput: isInputRelated,
                isOutput: isOutputRelated
            });
        }
    });
    
    return relevantReactions;
}

function checkTagMatch(tagPattern, materialName, hierarchy, xmlDoc) {
    const tag = tagPattern.slice(1, -1); // Enlever les crochets
    
    // Récupérer tous les matériaux à vérifier (cible + descendants)
    const materialsToCheck = getAllDescendants(hierarchy, materialName, true);
    
    for (const matName of materialsToCheck) {
        const materialElement = xmlDoc.querySelector(`[name="${matName}"]`);
        if (materialElement) {
            const tagsAttr = materialElement.getAttribute('tags');
            if (tagsAttr && tagsAttr.includes(`[${tag}]`)) {
                return true;
            }
        }
    }
    
    return false;
}

function formatReactions(reactions) {
    return reactions.map(({reaction, isInput, isOutput}) => {
        return {
            probability: reaction.getAttribute('probability'),
            input: [
                reaction.getAttribute('input_cell1'),
                reaction.getAttribute('input_cell2'),
                reaction.getAttribute('input_cell3')
            ].filter(Boolean),
            output: [
                reaction.getAttribute('output_cell1'),
                reaction.getAttribute('output_cell2'),
                reaction.getAttribute('output_cell3')
            ].filter(Boolean),
            isInputReaction: isInput,
            isOutputReaction: isOutput
        };
    });
}

function findMaterialReactions(xmlContent, targetMaterial) {
    const xmlDoc = parseMaterialsXML(xmlContent);
    const hierarchy = buildMaterialHierarchy(xmlDoc);
    const reactions = getReactionsForMaterial(xmlDoc, hierarchy, targetMaterial);
    return formatReactions(reactions);
}