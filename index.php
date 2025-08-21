<!DOCTYPE html>
<html lang='fr'>
<head>
    <link rel='stylesheet' href='style.css'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>NoitAlambic - POC</title>
</head>
<body>
    <div class="container">       
        <div class="header">
            <h1>NoitAlambic - POC</h1>
            <div class="controls">
                <button onclick="togglePanel('hierarchy-panel')">Afficher/Masquer Hiérarchie</button>
                <button onclick="togglePanel('info-panel')">Afficher/Masquer Informations</button>
                <button onclick="togglePanel('graph-container')">Afficher/Masquer Graphique</button>
                <label>
                    <input type="checkbox" id="show-indirect" onchange="onAlchimicSelection()" checked>
                    Afficher les réactions indirectes (tags)
                </label>
            </div>
        </div>

        <div class="node-info">
            <select id="material-select" onchange="onMaterialSelected()">
                <option value="">-- Choisir un matériau --</option>
            </select>

            <select id="tag-select" onchange="onTagSelected()">
                <option value="">-- Choisir un tag --</option>
            </select>
        </div>

        <div class="content">
            <div class="container">
                <div class="hierarchy-panel" id="hierarchy-panel">
                    <h2>Hiérarchie du matériau</h2>
                    <div id="hierarchy-info"></div>
                    <!-- Nouveau conteneur pour le graphique de hiérarchie -->
                    <div id="hierarchy-graph-container" style="margin-top: 15px; min-height: 300px; border: 1px solid #444; border-radius: 5px; overflow: auto;"></div>
                </div>

                <div class="info-panel" id="info-panel">
                    <h2>Informations</h2>
                    <div class="node-info">
                        <h3>Matériau sélectionné :</h3>
                        <p id="selected-material">Aucun matériau sélectionné</p>
                        <h3>Tags :</h3>
                        <div id="tags-info"></div>
                        <h3>Réactions :</h3>
                        <div id="reactions-info"></div>
                    </div>
                </div>
            </div>

            <div class="graph-container" id="graph-container">
                <h2>Graphique des réactions de matériaux</h2>
                <div id="graphviz-container">
                    <!-- Le graphique sera chargé ici -->
                </div>
            </div>
        </div>
    </div>

    <!-- Charger les scripts nécessaires -->
    <script src="hierarchyBuilder.js"></script>
    <script>
        let materialsData = null;
        hierarchy = null;
        let currentMaterial = '';
        let currentTag = '';
        let allTags = [];

        // Fonction pour parser le XML (simulée car non fournie dans hierarchyBuilder.js)
        function parseMaterialsXML(xmlContent) {
            // Cette fonction devrait normalement parser le XML
            // Pour cette démo, nous allons créer un faux document XML
            const parser = new DOMParser();
            return parser.parseFromString(xmlContent, "text/xml");
        }

        // Charger les données des matériaux (normalement depuis un fichier ou API)
        async function loadMaterialsData() {
            // Dans une implémentation réelle, on chargerait le XML depuis un fichier
            // Pour cette démo, nous allons créer des données factices
            const response = await fetch('assets/materials.xml');
            const xmlContent = await response.text();
            
            materialsData = parseMaterialsXML(xmlContent);
            hierarchy = buildMaterialHierarchy(materialsData);
            
            // Remplir le sélecteur de matériaux
            const materialSelect = document.getElementById('material-select');
            materialSelect.innerHTML = '<option value="">-- Choisir un matériau --</option>';
            
            for (const materialName in hierarchy) {
                const option = document.createElement('option');
                option.value = materialName;
                option.textContent = materialName;
                materialSelect.appendChild(option);
            }
            
            // Remplir le sélecteur de tags
            allTags = getAllTags(materialsData);
            const tagSelect = document.getElementById('tag-select');
            tagSelect.innerHTML = '<option value="">-- Choisir un tag --</option>';
            
            allTags.forEach(tag => {
                const option = document.createElement('option');
                option.value = tag;
                option.textContent = tag;
                tagSelect.appendChild(option);
            });
        }

        // Obtenir tous les tags disponibles
        function getAllTags(xmlDoc) {
            const tags = new Set();
            const materials = xmlDoc.querySelectorAll('CellData, CellDataChild');
            
            materials.forEach(material => {
                const tagsAttr = material.getAttribute('tags');
                if (tagsAttr) {
                    const tagMatches = tagsAttr.match(/\[[^\]]+\]/g);
                    if (tagMatches) {
                        tagMatches.forEach(tag => tags.add(tag));
                    }
                }
            });
            
            return Array.from(tags);
        }

        // Basculer la visibilité d'un panneau
        function togglePanel(panelId) {
            const panel = document.getElementById(panelId);
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        // Générer le graphique GraphViz pour un matériau spécifique
        function generateReactionsGraph(materialName, showIndirect = true) {
            if (!materialName) return "digraph { node [shape=box]; \"Sélectionnez un matériau\"; }";
            
            const reactions = getReactionsForMaterial(materialsData, hierarchy, materialName);
            const formattedReactions = formatReactions(reactions);
            
            let dot = "digraph {";
            dot += "  splines=ortho;";
            dot += "  rankdir=\"BT\";";
            dot += "  ratio=0.5;";
            dot += "  bgcolor=\"#333333\";";
            dot += "  node [shape=box, style=filled, color=lightblue, onclick=\"nodeClick(this.innerHTML)\", width=0.8, height=0.5];"; // Taille réduite des nodes
            dot += "  edge [fontsize=10];";
            
            // Ajouter le matériau central
            dot += `  "${materialName}" [fillcolor=lightcoral, URL="javascript:void(0);"];`;
            
            // Ensemble pour suivre les nœuds déjà ajoutés
            const addedNodes = new Set([materialName]);
            
            // Parcourir les réactions pour ajouter les nœuds et les arêtes
            formattedReactions.forEach((reaction, index) => {
                // Vérifier si c'est une réaction indirecte (via tag)
                const isIndirect = reaction.input.some(cell => cell.startsWith('[') && cell.endsWith(']')) || 
                                  reaction.output.some(cell => cell.startsWith('[') && cell.endsWith(']'));
                
                // Si on ne veut pas afficher les réactions indirectes et que c'est indirect, on skip
                if (!showIndirect && isIndirect) return;
                
                // Ajouter les nœuds d'entrée
                reaction.input.forEach(input => {
                    if (!addedNodes.has(input)) {
                        dot += `  "${input}" [URL="javascript:void(0);"];`;
                        addedNodes.add(input);
                    }
                });
                
                // Ajouter les nœuds de sortie
                reaction.output.forEach(output => {
                    if (!addedNodes.has(output)) {
                        dot += `  "${output}" [URL="javascript:void(0);"];`;
                        addedNodes.add(output);
                    }
                });
                
                // Créer un nœud pour la réaction avec un label court et un tooltip détaillé (label avec title)
                const reactionNode = `reaction_${index}`;
                const inputLabel = reaction.input.join(' + ');
                const outputLabel = reaction.output.join(' + ');
                const reactionLabel = 'Réaction';
                const reactionTooltip = `${inputLabel} → ${outputLabel} (Probabilité: ${reaction.probability})`;
                dot += `  "${reactionNode}" [shape=box, label="${reactionLabel}", style=filled, fillcolor="#f9f9a9", fontcolor="#333333", tooltip="${reactionTooltip}"];`;
                
                // Relier les entrées à la réaction (en bleu pour les inputs)
                reaction.input.forEach(input => {
                    dot += `  "${input}" -> "${reactionNode}" [dir=none, color="${isIndirect ? 'orange' : 'lightblue'}"];`;
                });
                
                // Relier la réaction aux sorties (en vert pour les outputs)
                reaction.output.forEach(output => {
                    dot += `  "${reactionNode}" -> "${output}" [color="${isIndirect ? 'orange' : 'green'}"];`;
                });
                
                // Ajouter la probabilité comme label sur l'arête de sortie principale
                if (reaction.output.length > 0) {
                    dot += `  "${reactionNode}" -> "${reaction.output[0]}" [label="${reaction.probability}"];`;
                }
            });
            
            dot += "}";
            return dot;
        }

        // Générer le graphique de hiérarchie avec tags
        function generateMaterialHierarchyGraph(materialName) {
            if (!materialName) return "digraph { node [shape=box]; \"Sélectionnez un matériau\"; }";
            
            const genealogy = getMaterialGenealogy(hierarchy, materialName);
            
            let dot = "digraph {";
            dot += "  bgcolor=\"#333333\";";
            dot += "  splines=ortho;";
            dot += "  ratio=0.5;";
            dot += "  node [shape=box, style=filled, color=lightblue, onclick=\"nodeClick(this.innerHTML)\"];";
            dot += "  edge [fontsize=10, color=white];";
            
            // Ajouter tous les matériaux de la généalogie (sans les tags dans le label)
            genealogy.forEach((material, index) => {
                let nodeAttributes = `URL="javascript:void(0);"`;
                
                if (material === materialName) {
                    nodeAttributes += `, fillcolor="#c06cbfff"`; // Matériau sélectionné en violet
                } else if (index === 0) {
                    nodeAttributes += `, fillcolor="#6cc06fff"`; // Racine en vert
                } else {
                    nodeAttributes += `, fillcolor="#6cb0ffff"`; // Autres matériaux en bleu
                }
                
                dot += `  "${material}" [label="${material}", ${nodeAttributes}];`;
            });
            
            // Créer les relations hiérarchiques
            for (let i = 0; i < genealogy.length - 1; i++) {
                dot += `  "${genealogy[i + 1]}" -> "${genealogy[i]}";`;
            }
            
            // Ajouter les tags comme nœuds séparés et les relier aux matériaux
            const addedTags = new Set();
            genealogy.forEach(material => {
                const tags = getMaterialTags(materialsData, material);
                tags.forEach(tag => {
                    if (!addedTags.has(tag)) {
                        dot += `  "${tag}" [shape=box, fillcolor=orange, style=filled, color=black, URL="javascript:void(0);"];`;
                        addedTags.add(tag);
                    }
                    dot += `  "${material}" -> "${tag}" [style=dashed, color=orange, dir=none, constraint=false];`;
                });
            });
            
            dot += "}";
            return dot;
        }
        // Nouvelle fonction pour générer le graphique de hiérarchie pour un tag
        function generateTagHierarchyGraph(tag) {
            if (!tag) return "digraph { node [shape=box]; \"Sélectionnez un tag\"; }";
            
            // Trouver tous les matériaux avec ce tag
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
                return "digraph { node [shape=box]; \"Aucun matériau avec ce tag\"; }";
            }
            
            let dot = "digraph {";
            dot += "  bgcolor=\"#333333\";";
            dot += "  rankdir=\"BT\";";
            dot += "  splines=ortho;";
            dot += "  node [shape=box, style=filled, onclick=\"nodeClick(this.innerHTML)\"];";
            dot += "  edge [fontsize=10, color=white];";
            
            // Ajouter le tag comme nœud central
            dot += `  "${tag}" [fillcolor=orange, URL="javascript:void(0);"];`;
            
            // Ajouter tous les matériaux avec ce tag
            materialsWithTag.forEach(material => {
                // Obtenir la hiérarchie complète de chaque matériau
                const genealogy = getMaterialGenealogy(hierarchy, material);
                
                // Ajouter tous les matériaux de la hiérarchie
                genealogy.forEach((mat, index) => {
                    let nodeAttributes = `URL="javascript:void(0);"`;
                    
                    if (mat === material) {
                        nodeAttributes += `, fillcolor="#c06cbfff"`; // Matériau avec le tag en violet
                    } else if (index === 0) {
                        nodeAttributes += `, fillcolor="#6cc06fff"`; // Racine en vert
                    } else {
                        nodeAttributes += `, fillcolor="#6cb0ffff"`; // Autres matériaux en bleu
                    }
                    
                    dot += `  "${mat}" [label="${mat}", ${nodeAttributes}];`;
                });
                
                // Créer les relations hiérarchiques
                for (let i = 0; i < genealogy.length - 1; i++) {
                    dot += `  "${genealogy[i + 1]}" -> "${genealogy[i]}";`;
                }
                
                // Relier le matériau au tag
                dot += `  "${material}" -> "${tag}" [style=dashed, color=orange, dir=none, constraint=false];`;
            });
            
            dot += "}";
            return dot;
        }
        // Gestionnaire de clic sur un nœud - VERSION AMÉLIORÉE
        function nodeClick(nodeName) {
            // Vérifier si c'est un tag (entre crochets)
            if (nodeName.startsWith('[') && nodeName.endsWith(']')) {
                document.getElementById('tag-select').value = nodeName;
                document.getElementById('material-select').value = '';
            } else {
                // C'est un matériau
                document.getElementById('material-select').value = nodeName;
                document.getElementById('tag-select').value = '';
            }
            
            // Utiliser la fonction générique
            onAlchimicSelection();
        }

        // Fonction pour attacher les événements de clic aux nœuds SVG
        function attachNodeClickEvents(containerId) {
            const svgElement = document.querySelector(`#${containerId} svg`);
            if (!svgElement) return;
            
            // Ajouter un style de curseur pointer à tous les nœuds de texte (sauf les réactions)
            const textNodes = svgElement.querySelectorAll('text');
            textNodes.forEach(textNode => {
                const textContent = textNode.textContent;
                // Ne pas rendre les réactions cliquables
                if (!textContent.startsWith('Réaction') && textContent !== '') {
                    // Trouver l'élément parent (généralement un groupe)
                    let parent = textNode.parentElement;
                    while (parent && parent.nodeName !== 'g') {
                        parent = parent.parentElement;
                    }
                    
                    if (parent) {
                        parent.style.cursor = 'pointer';
                        parent.addEventListener('click', function() {
                            // Pour les labels multi-lignes, prendre seulement la première ligne (le nom du matériau)
                            const firstLine = textContent.split('\n')[0];
                            nodeClick(firstLine);
                        });
                    }
                }
            });
        }

        // Mettre à jour la fonction renderGraphviz pour supporter différents conteneurs

        function renderGraphviz(dotCode, containerId = 'graphviz-container') {
            const viz = new Viz();
            
            viz.renderSVGElement(dotCode)
                .then(element => {
                    const container = document.getElementById(containerId);
                    container.innerHTML = '';
                    container.appendChild(element);
                    
                    // Attacher les événements de clic après un court délai
                    setTimeout(() => attachNodeClickEvents(containerId), 100);
                })
                .catch(error => {
                    console.error('Erreur lors du rendu GraphViz:', error);
                    document.getElementById(containerId).innerHTML = 
                        '<p>Erreur lors du rendu du graphique. Assurez-vous que Viz.js est chargé.</p>';
                });
        }

        function onAlchimicSelection() {
            const materialSelect = document.getElementById('material-select');
            const tagSelect = document.getElementById('tag-select');
            
            // Déterminer ce qui a été sélectionné
            if (materialSelect.value) {
                // Un matériau est sélectionné
                currentMaterial = materialSelect.value;
                currentTag = '';
                tagSelect.value = '';
                
                document.getElementById('selected-material').textContent = currentMaterial;
                
                // Afficher la hiérarchie
                displayHierarchyInfo(currentMaterial);
                
                // Afficher les tags
                displayTagsInfo(currentMaterial);
                
                // Générer et afficher le graphique de hiérarchie
                const hierarchyDot = generateMaterialHierarchyGraph(currentMaterial);
                renderGraphviz(hierarchyDot, 'hierarchy-graph-container');
                
                // Générer et afficher le graphique des réactions
                const showIndirect = document.getElementById('show-indirect').checked;
                const reactionsDot = generateReactionsGraph(currentMaterial, showIndirect);
                renderGraphviz(reactionsDot, 'graphviz-container');
                
                // Afficher les informations sur les réactions
                displayReactionsInfo(currentMaterial);
                
            } else if (tagSelect.value) {
                // Un tag est sélectionné
                currentTag = tagSelect.value;
                currentMaterial = '';
                materialSelect.value = '';
                
                document.getElementById('selected-material').textContent = `Tag: ${currentTag}`;
                
                // Afficher les matériaux avec ce tag
                displayMaterialsForTag(currentTag);
                
                // Générer et afficher le graphique de hiérarchie pour le tag
                const hierarchyDot = generateTagHierarchyGraph(currentTag);
                renderGraphviz(hierarchyDot, 'hierarchy-graph-container');
                
                // Générer et afficher le graphique pour le tag
                const showIndirect = document.getElementById('show-indirect').checked;
                const dot = generateTagGraph(currentTag, showIndirect);
                renderGraphviz(dot, 'graphviz-container');
                
                // Afficher les informations sur les réactions pour le tag
                displayTagReactionsInfo(currentTag);
                
            } else {
                // Rien n'est sélectionné
                document.getElementById('selected-material').textContent = 'Aucune sélection';
                document.getElementById('hierarchy-info').innerHTML = '';
                document.getElementById('hierarchy-graph-container').innerHTML = '';
                document.getElementById('tags-info').innerHTML = '';
                document.getElementById('reactions-info').innerHTML = '';
                document.getElementById('graphviz-container').innerHTML = '';
            }
        }

        function displayMaterialsForTag(tag) {
            const tagsInfo = document.getElementById('tags-info');
            tagsInfo.innerHTML = '';
            
            const hierarchyInfo = document.getElementById('hierarchy-info');
            hierarchyInfo.innerHTML = '<h3>Matériaux avec ce tag:</h3>';
            
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
                hierarchyInfo.innerHTML += '<p>Aucun matériau avec ce tag</p>';
                return;
            }
            
            const list = document.createElement('ul');
            materialsWithTag.forEach(material => {
                const item = document.createElement('li');
                item.textContent = material;
                list.appendChild(item);
            });
            
            hierarchyInfo.appendChild(list);
        }
        // Fonctions spécifiques conservées pour compatibilité (mais elles appellent la fonction générique)
        function onMaterialSelected() {
            // Effacer la sélection de tag
            document.getElementById('tag-select').value = '';
            onAlchimicSelection();
        }

        function onTagSelected() {
            // Effacer la sélection de matériau
            document.getElementById('material-select').value = '';
            onAlchimicSelection();
        }

        // Afficher la hiérarchie du matériau
        function displayHierarchyInfo(materialName) {
            const hierarchyInfo = document.getElementById('hierarchy-info');
            hierarchyInfo.innerHTML = '';
            
            const genealogy = getMaterialGenealogy(hierarchy, materialName);
            
            if (genealogy.length <= 1) {
                hierarchyInfo.innerHTML = '<p>Ce matériau n\'a pas de parent</p>';
                return;
            }
            
            const list = document.createElement('ul');
            for (let i = 0; i < genealogy.length; i++) {
                const item = document.createElement('li');
                if (i === genealogy.length - 1) {
                    item.innerHTML = `<strong>${genealogy[i]}</strong>`;
                } else {
                    item.textContent = genealogy[i];
                }
                list.appendChild(item);
            }
            
            hierarchyInfo.appendChild(list);
        }

        // Afficher les tags du matériau
        function displayTagsInfo(materialName) {
            const tagsInfo = document.getElementById('tags-info');
            tagsInfo.innerHTML = '';
            
            const tags = getMaterialTags(materialsData, materialName);
            
            if (tags.length === 0) {
                tagsInfo.innerHTML = '<p>Ce matériau n\'a pas de tags</p>';
                return;
            }
            
            const list = document.createElement('ul');
            tags.forEach(tag => {
                const item = document.createElement('li');
                item.textContent = tag;
                list.appendChild(item);
            });
            
            tagsInfo.appendChild(list);
        }

        // Afficher les matériaux ayant un tag spécifique
        function displayMaterialsForTag(tag) {
            const tagsInfo = document.getElementById('tags-info');
            tagsInfo.innerHTML = '';
            
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
                tagsInfo.innerHTML = '<p>Aucun matériau avec ce tag</p>';
                return;
            }
            
            const list = document.createElement('ul');
            materialsWithTag.forEach(material => {
                const item = document.createElement('li');
                item.textContent = material;
                list.appendChild(item);
            });
            
            tagsInfo.appendChild(list);
        }

        // Afficher les informations sur les réactions
        function displayReactionsInfo(materialName) {
            const reactions = getReactionsForMaterial(materialsData, hierarchy, materialName);
            const formattedReactions = formatReactions(reactions);
            const showIndirect = document.getElementById('show-indirect').checked;
            
            const reactionsContainer = document.getElementById('reactions-info');
            reactionsContainer.innerHTML = '';
            
            if (formattedReactions.length === 0) {
                reactionsContainer.innerHTML = '<p>Aucune réaction trouvée pour ce matériau.</p>';
                return;
            }
            
            const list = document.createElement('ul');
            formattedReactions.forEach(reaction => {
                // Vérifier si c'est une réaction indirecte (via tag)
                const isIndirect = reaction.input.some(cell => cell.startsWith('[') && cell.endsWith(']')) || 
                                  reaction.output.some(cell => cell.startsWith('[') && cell.endsWith(']'));
                
                // Si on ne veut pas afficher les réactions indirectes et que c'est indirect, on skip
                if (!showIndirect && isIndirect) return;
                
                const item = document.createElement('li');
                
                let reactionText = '';
                if (reaction.isInputReaction) {
                    reactionText = `${reaction.input.join(' + ')} → ${reaction.output.join(' + ')} (Probabilité: ${reaction.probability})`;
                } else if (reaction.isOutputReaction) {
                    reactionText = `${reaction.input.join(' + ')} → ${reaction.output.join(' + ')} (Probabilité: ${reaction.probability})`;
                }
                
                if (isIndirect) {
                    item.innerHTML = `<em>${reactionText} (via tag)</em>`;
                } else {
                    item.textContent = reactionText;
                }
                
                list.appendChild(item);
            });
            
            reactionsContainer.appendChild(list);
        }

        // Afficher les informations sur les réactions pour un tag
        function displayTagReactionsInfo(tag) {
            const reactionsContainer = document.getElementById('reactions-info');
            reactionsContainer.innerHTML = '';
            
            // Trouver toutes les réactions qui impliquent ce tag
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
                        reaction: reaction,
                        isInput: isInputRelated,
                        isOutput: isOutputRelated
                    });
                }
            });
            
            const formattedReactions = formatReactions(relevantReactions);
            
            if (formattedReactions.length === 0) {
                reactionsContainer.innerHTML = '<p>Aucune réaction trouvée pour ce tag.</p>';
                return;
            }
            
            const list = document.createElement('ul');
            formattedReactions.forEach(reaction => {
                const item = document.createElement('li');
                
                let reactionText = '';
                if (reaction.isInputReaction) {
                    reactionText = `${reaction.input.join(' + ')} → ${reaction.output.join(' + ')} (Probabilité: ${reaction.probability})`;
                } else if (reaction.isOutputReaction) {
                    reactionText = `${reaction.input.join(' + ')} → ${reaction.output.join(' + ')} (Probabilité: ${reaction.probability})`;
                }
                
                item.textContent = reactionText;
                list.appendChild(item);
            });
            
            reactionsContainer.appendChild(list);
        }

        // Générer un graphique pour un tag
        function generateTagGraph(tag, showIndirect = true) {
            if (!tag) return "digraph { node [shape=box]; \"Sélectionnez un tag\"; }";
            
            // Trouver toutes les réactions qui impliquent ce tag
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
                        reaction: reaction,
                        isInput: isInputRelated,
                        isOutput: isOutputRelated
                    });
                }
            });
            
            const formattedReactions = formatReactions(relevantReactions);
            
            let dot = "digraph {";
            dot += "  splines=ortho;";
            dot += "  rankdir=\"LR\";";
            dot += "  bgcolor=\"#333333\";";
            dot += "  node [shape=box, style=filled, color=lightblue, onclick=\"nodeClick(this.innerHTML)\", width=0.8, height=0.5];";
            dot += "  edge [fontsize=10];";
            
            // Ajouter le tag central
            dot += `  "${tag}" [fillcolor=orange, URL="javascript:void(0);"];`;
            
            // Ensemble pour suivre les nœuds déjà ajoutés
            const addedNodes = new Set([tag]);
            
            // Parcourir les réactions pour ajouter les nœuds et les arêtes
            formattedReactions.forEach((reaction, index) => {
                // Ajouter les nœuds d'entrée
                reaction.input.forEach(input => {
                    if (input !== tag && !addedNodes.has(input)) {
                        dot += `  "${input}" [URL="javascript:void(0);"];`;
                        addedNodes.add(input);
                    }
                });
                
                // Ajouter les nœuds de sortie
                reaction.output.forEach(output => {
                    if (output !== tag && !addedNodes.has(output)) {
                        dot += `  "${output}" [URL="javascript:void(0);"];`;
                        addedNodes.add(output);
                    }
                });
                
                // Créer un nœud pour la réaction
                const reactionNode = `reaction_${index}`;
                const inputLabel = reaction.input.join(' + ');
                const outputLabel = reaction.output.join(' + ');
                const reactionLabel = 'Réaction';
                const reactionTooltip = `${inputLabel} → ${outputLabel} (Probabilité: ${reaction.probability})`;
                dot += `  "${reactionNode}" [shape=box, label="${reactionLabel}", style=filled, fillcolor=\"#f9f9a9\", fontcolor="#333", tooltip="${reactionTooltip}"];`;
                
                // Relier les entrées à la réaction
                reaction.input.forEach(input => {
                    const color = input === tag ? 'orange' : 'lightblue';
                    dot += `  "${input}" -> "${reactionNode}" [dir=none, color="${color}"];`;
                });
                
                // Relier la réaction aux sorties
                reaction.output.forEach(output => {
                    const color = output === tag ? 'orange' : 'green';
                    dot += `  "${reactionNode}" -> "${output}" [color="${color}"];`;
                });
                
                // Ajouter la probabilité comme label sur l'arête de sortie principale
                if (reaction.output.length > 0) {
                    dot += `  "${reactionNode}" -> "${reaction.output[0]}" [label="${reaction.probability}"];`;
                }
            });
            
            dot += "}";
            return dot;
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Charger Viz.js dynamiquement
            const script = document.createElement('script');
            script.src = 'viz.js';
            script.onload = function() {
                const script2 = document.createElement('script');
                script2.src = 'full.render.js';
                script2.onload = function() {
                    // Charger les données des matériaux
                    loadMaterialsData();
                };
                document.head.appendChild(script2);
            };
            document.head.appendChild(script);
        });
    </script>
</body>
</html>