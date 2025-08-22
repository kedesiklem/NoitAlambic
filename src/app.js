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

        // Fonctions spécifiques pour gérer les sélections
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

        // Modifier la fonction onAlchimicSelection pour qu'elle soit plus robuste
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
                // Utiliser isDirect au lieu de vérifier les crochets
                const isIndirect = !reaction.isDirect;
                
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

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Charger Viz.js dynamiquement
            const script = document.createElement('script');
            script.src = 'external/viz.js';
            script.onload = function() {
                const script2 = document.createElement('script');
                script2.src = 'external/full.render.js';
                script2.onload = function() {
                    // Charger les données des matériaux
                    loadMaterialsData();
                };
                document.head.appendChild(script2);
            };
            document.head.appendChild(script);
        });