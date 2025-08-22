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

function getMaterialAndAncestors(hierarchy, materialName, includeSelf = true) {
    const materials = includeSelf ? [materialName] : [];
    
    function getParents(name) {
        const parentName = hierarchy[name]?.parent;
        if (parentName && hierarchy[parentName]) {
            materials.push(parentName);
            getParents(parentName);
        }
    }
    
    if (hierarchy[materialName]) {
        getParents(materialName);
    }
    
    return [...new Set(materials)];
}

function getMaterialGenealogy(hierarchy, materialName) {
    const genealogy = [materialName];
    
    function getParentLine(name) {
        const parentName = hierarchy[name]?.parent;
        if (parentName && hierarchy[parentName]) {
            genealogy.unshift(parentName);
            getParentLine(parentName);
        }
    }
    
    if (hierarchy[materialName]) {
        getParentLine(materialName);
    }
    
    return genealogy;
}

function getReactionsForMaterial(xmlDoc, hierarchy, targetMaterial) {
    const reactions = xmlDoc.querySelectorAll('Reaction');
    const relevantReactions = [];
    
    const materialsToCheck = getMaterialAndAncestors(hierarchy, targetMaterial, true);
    
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
        
        // Vérifier d'abord la présence DIRECTE du matériau
        const hasDirectInput = inputCells.includes(targetMaterial);
        const hasDirectOutput = outputCells.includes(targetMaterial);
        
        // Si le matériau est présent directement, c'est une réaction directe
        if (hasDirectInput || hasDirectOutput) {
            relevantReactions.push({
                reaction: reaction,
                isInput: hasDirectInput,
                isOutput: hasDirectOutput,
                isDirect: true // Ajouter un flag pour indiquer que c'est direct
            });
        } 
        // Sinon, vérifier les tags (réaction indirecte)
        else {
            const isInputRelated = inputCells.some(cell => 
                cell.startsWith('[') && cell.endsWith(']') && 
                checkTagMatch(cell, targetMaterial, hierarchy, xmlDoc)
            );
            
            const isOutputRelated = outputCells.some(cell => 
                cell.startsWith('[') && cell.endsWith(']') && 
                checkTagMatch(cell, targetMaterial, hierarchy, xmlDoc)
            );
            
            if (isInputRelated || isOutputRelated) {
                relevantReactions.push({
                    reaction: reaction,
                    isInput: isInputRelated,
                    isOutput: isOutputRelated,
                    isDirect: false // Indiquer que c'est indirect
                });
            }
        }
    });
    
    return relevantReactions;
}

function checkTagMatch(tagPattern, materialName, hierarchy, xmlDoc) {
    const tag = tagPattern.slice(1, -1);
    const materialsToCheck = getMaterialAndAncestors(hierarchy, materialName, true);
    
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
    return reactions.map(({reaction, isInput, isOutput, isDirect}) => {
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
            isOutputReaction: isOutput,
            isDirect: isDirect // Nouvelle propriété
        };
    });
}

function getMaterialTags(xmlDoc, materialName) {
    const materialElement = xmlDoc.querySelector(`[name="${materialName}"]`);
    if (!materialElement) return [];
    
    const tagsAttr = materialElement.getAttribute('tags');
    if (!tagsAttr) return [];
    
    const tagMatches = tagsAttr.match(/\[[^\]]+\]/g);
    return tagMatches ? tagMatches : [];
}

function findMaterialReactions(xmlContent, targetMaterial) {
    const xmlDoc = parseMaterialsXML(xmlContent);
    const hierarchy = buildMaterialHierarchy(xmlDoc);
    const reactions = getReactionsForMaterial(xmlDoc, hierarchy, targetMaterial);
    return formatReactions(reactions);
}