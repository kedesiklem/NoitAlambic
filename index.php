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
                    <div id="hierarchy-graph-container"></div>
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

<script src="src/hierarchyBuilder.js"></script>
<script src="external/viz.js"></script>
<script src="external/full.render.js"></script>
<script src="external/svg-pan-zoom.min.js"></script>

<script src="src/graphBuilder.js"></script>
<script src="src/app.js"></script>
<script src="src/zoom-pan.js"></script>
</body>
</html>