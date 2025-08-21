<!DOCTYPE html>
<html lang='fr'>
<head>
    <link rel='stylesheet' href='style.css'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>NoitAlambic - POC</title>
</head>
<body>
    <div class="container">       
        <div class="content">
            <div class="info-panel">
                <h2>Informations</h2>
                <div class="node-info">
                    <h3>Matériau sélectionné :</h3>
                    <p id="selected-material">Aucun matériau sélectionné</p>
                    <h3>Réactions :</h3>
                    <div id="reactions-info"></div>
                </div>
            </div>

            <div class="graph-container">
                <h2>Graphique des réactions de matériaux</h2>
                <div>
                    <label for="material-select">Sélectionner un matériau: </label>
                    <select id="material-select" onchange="onMaterialSelected()">
                        <option value="">-- Choisir un matériau --</option>
                    </select>
                </div>
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
        let hierarchy = null;
        let currentMaterial = '';

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
        }

        // Générer le graphique GraphViz pour un matériau spécifique
        function generateReactionsGraph(materialName) {
            if (!materialName) return "digraph { node [shape=box]; \"Sélectionnez un matériau\"; }";
            
            const reactions = getReactionsForMaterial(materialsData, hierarchy, materialName);
            const formattedReactions = formatReactions(reactions);
            
            let dot = "digraph {";
            dot += "  splines=ortho;";
            dot += "  rankdir=\"LR\";";
            dot += "  bgcolor=\"#333333\";";
            dot += "  node [shape=box, style=filled, color=lightblue, onclick=\"nodeClick(this.innerHTML)\", width=0.8, height=0.5];"; // Taille réduite des nodes
            dot += "  edge [color=gray40, fontsize=10];";
            
            // Ajouter le matériau central
            dot += `  "${materialName}" [fillcolor=lightcoral, URL="javascript:void(0);"];`;
            
            // Ensemble pour suivre les nœuds déjà ajoutés
            const addedNodes = new Set([materialName]);
            
            // Parcourir les réactions pour ajouter les nœuds et les arêtes
            formattedReactions.forEach((reaction, index) => {
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
                dot += `  "${reactionNode}" [shape=box, label="${reactionLabel}", style=filled, fillcolor="#f9f9a9", fontcolor="#333", tooltip="${reactionTooltip}"];`;
                // Relier les entrées à la réaction
                reaction.input.forEach(input => {
                    dot += `  "${input}" -> "${reactionNode}" [dir=none];`;
                });
                
                // Relier la réaction aux sorties
                reaction.output.forEach(output => {
                    dot += `  "${reactionNode}" -> "${output}";`;
                });
                
                // Ajouter la probabilité comme label sur l'arête de sortie principale
                if (reaction.output.length > 0) {
                    dot += `  "${reactionNode}" -> "${reaction.output[0]}" [label="${reaction.probability}"];`;
                }
            });
            
            dot += "}";
            return dot;
        }

        // Rendre le graphique GraphViz
        function renderGraphviz(dotCode) {
            // Utilisation de Viz.js pour le rendu
            const viz = new Viz();
            
            viz.renderSVGElement(dotCode)
                .then(element => {
                    const container = document.getElementById('graphviz-container');
                    container.innerHTML = '';
                    container.appendChild(element);
                    
                    // Ajouter des écouteurs d'événements pour les nœuds
                    // pour les tag afficher les elements concerné
                    const nodes = element.querySelectorAll('[class^="node"] title');
                    nodes.forEach(node => {
                        const parent = node.parentElement;
                        if (node.textContent !== '' && !node.textContent.startsWith('reaction_')) {
                            parent.style.cursor = 'pointer';
                            parent.addEventListener('click', function() {
                                nodeClick(node.textContent);
                            });
                        }
                    });
                })
                .catch(error => {
                    console.error('Erreur lors du rendu GraphViz:', error);
                    document.getElementById('graphviz-container').innerHTML = 
                        '<p>Erreur lors du rendu du graphique. Assurez-vous que Viz.js est chargé.</p>';
                });
        }

        // Gestionnaire de clic sur un nœud
        function nodeClick(materialName) {
            document.getElementById('material-select').value = materialName;
            onMaterialSelected();
        }

        // Lorsqu'un matériau est sélectionné
        function onMaterialSelected() {
            const materialSelect = document.getElementById('material-select');
            currentMaterial = materialSelect.value;
            
            if (currentMaterial) {
                document.getElementById('selected-material').textContent = currentMaterial;
                
                // Générer et afficher le graphique
                const dot = generateReactionsGraph(currentMaterial);
                renderGraphviz(dot);
                
                // Afficher les informations sur les réactions
                displayReactionsInfo(currentMaterial);
            } else {
                document.getElementById('selected-material').textContent = 'Aucun matériau sélectionné';
                document.getElementById('reactions-info').innerHTML = '';
                document.getElementById('graphviz-container').innerHTML = '';
            }
        }

        // Afficher les informations sur les réactions
        function displayReactionsInfo(materialName) {
            const reactions = getReactionsForMaterial(materialsData, hierarchy, materialName);
            const formattedReactions = formatReactions(reactions);
            
            const reactionsContainer = document.getElementById('reactions-info');
            reactionsContainer.innerHTML = '';
            
            if (formattedReactions.length === 0) {
                reactionsContainer.innerHTML = '<p>Aucune réaction trouvée pour ce matériau.</p>';
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