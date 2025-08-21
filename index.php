<!DOCTYPE html>
<html lang='fr'>
<head>
<script>
MathJax = {
  tex: {
    inlineMath: {'[+]': [['$', '$']]}
  },
  svg: {
    fontCache: 'global'
  }
};
</script>
    <script type='text/javascript' id='MathJax-script' async
        src='https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js'>
    </script>
    <link rel='stylesheet' href='style.css'>

    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>NoitAlambic - POC</title>
</head>
<body>
    <div class="container">       
        <div class="content">
            <div class="graph-container">
                <h2>Graphique interactif</h2>
                <div id="graphviz-container">
                    <!-- Le graphique sera chargé ici -->
                </div>
                <div style="margin-top: 20px;">
                    <button onclick="generateRandomGraph()">Générer un nouveau graphique</button>
                </div>
            </div>
            
            <div class="info-panel">
                <h2>Informations</h2>
                <div class="node-info">
                    <h3>Nœud sélectionné :</h3>
                    <p id="selected-node">Aucun nœud sélectionné</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour générer un graphique GraphViz aléatoire
        function randomGraphviz(label) {
            const nbNodes = 10;
            const nodes = [];
            
            // Ajouter le nœud du label fourni
            nodes.push(label);
            
            // Ajouter des nœuds avec des noms aléatoires
            for (let i = 0; i < nbNodes; i++) {
                nodes.push("N" + Math.floor(Math.random() * 900 + 100));
            }
            
            // Construction du DOT
            let dot = "digraph {";
            dot += "  splines=ortho;"
            dot += "  rankdir=\"LR\";"
            dot += "  ratio=0.45;"
            dot += "  node [shape=box, style=filled, color=lightblue, onclick=\"nodeClick(this.innerHTML)\"];";
            dot += "  edge [color=gray40];";
            
            // Déclaration des nœuds
            nodes.forEach(n => {
                dot += `  "${n}" [URL="javascript:void(0);"];`;
            });
            
            // Ajout d'arêtes aléatoires
            const edgesCount = Math.floor(Math.random() * (nodes.length * 2)) + nodes.length;
            const addedEdges = new Set();
            
            for (let i = 0; i < edgesCount; i++) {
                const from = nodes[Math.floor(Math.random() * nodes.length)];
                const to = nodes[Math.floor(Math.random() * nodes.length)];
                
                if (from !== to && !addedEdges.has(from + to)) {
                    dot += `  "${from}" -> "${to}";`;
                    addedEdges.add(from + to);
                }
            }
            
            dot += "}";
            return dot;
        }
        
        // Fonction pour rendre le graphique GraphViz
        function renderGraphviz(dotCode) {
            // Utilisation de Viz.js pour le rendu
            const viz = new Viz();
            
            viz.renderSVGElement(dotCode)
                .then(element => {
                    const container = document.getElementById('graphviz-container');
                    container.innerHTML = '';
                    container.appendChild(element);
                    
                    // Ajouter des écouteurs d'événements pour les nœuds
                    const nodes = element.querySelectorAll('[class^="node"] title');
                    nodes.forEach(node => {
                        const parent = node.parentElement;
                        parent.style.cursor = 'pointer';
                        parent.addEventListener('click', function() {
                            nodeClick(node.textContent);
                        });
                    });
                })
                .catch(error => {
                    console.error('Erreur lors du rendu GraphViz:', error);
                    document.getElementById('graphviz-container').innerHTML = 
                        '<p>Erreur lors du rendu du graphique. Assurez-vous que Viz.js est chargé.</p>';
                });
        }
        
        // Gestionnaire de clic sur un nœud
        function nodeClick(label) {
            document.getElementById('selected-node').textContent = label;
            const newDot = randomGraphviz(label);
            renderGraphviz(newDot);
        }
        
        // Générer un graphique aléatoire initial
        function generateRandomGraph() {
            document.getElementById('selected-node').textContent = 'Aucun nœud sélectionné';
            const initialLabel = 'F';
            const dot = randomGraphviz(initialLabel);
            renderGraphviz(dot);
        }
        
        // Charger Viz.js et initialiser le graphique
        document.addEventListener('DOMContentLoaded', function() {
            // Charger Viz.js dynamiquement
            const script = document.createElement('script');
            script.src = 'viz.js';
            script.onload = function() {
                const script2 = document.createElement('script');
                script2.src = 'full.render.js';
                script2.onload = generateRandomGraph;
                document.head.appendChild(script2);
            };
            document.head.appendChild(script);
        });
    </script>
</body>
</html>