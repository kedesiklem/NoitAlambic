// Auto-enable pan & zoom on any SVG inserted into the page.
// Requires svg-pan-zoom (MIT) to be loaded beforehand.
(function(){
  function enhance(svg){
    if (!svg || svg.__pz__) return;
    try {
      // Configuration pour s'adapter au conteneur
      svg.style.width = '100%';
      svg.style.height = '100%';
      svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
      
      // Créer un conteneur principal avec position relative
      const mainContainer = document.createElement('div');
      mainContainer.style.cssText = 'position: relative; display: flex; flex-direction: column;';
      
      // Créer un wrapper pour le SVG qui prend tout l'espace disponible
      const svgWrapper = document.createElement('div');
      svgWrapper.style.cssText = 'flex: 1; position: relative; overflow: hidden;';
      
      // Créer les contrôles personnalisés positionnés dans le coin
      const controlsDiv = document.createElement('div');
      controlsDiv.style.cssText = `
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1000;
        background: rgba(0,0,0,0.7);
        border-radius: 4px;
        padding: 4px;
      `;
      
      // Insérer le conteneur principal avant le SVG
      svg.parentNode.insertBefore(mainContainer, svg);
      
      // Ajouter le wrapper SVG et les contrôles au conteneur principal
      mainContainer.appendChild(svgWrapper);
      mainContainer.appendChild(controlsDiv);
      svgWrapper.appendChild(svg);
      
      // Boutons de contrôle
      const controls = [
        { text: '+', action: 'zoomIn', title: 'Zoom avant' },
        { text: '-', action: 'zoomOut', title: 'Zoom arrière' },
        { text: '⤢', action: 'fit', title: 'Ajuster à la fenêtre' },
        { text: '↺', action: 'reset', title: 'Réinitialiser' }
      ];
      
      // Initialiser svg-pan-zoom
      const instance = svgPanZoom(svg, {
        zoomEnabled: true,
        controlIconsEnabled: false, // Désactiver les contrôles par défaut
        fit: true,
        center: true,
        minZoom: 0.1,
        maxZoom: 50,
        // S'adapter automatiquement au conteneur
        beforeZoom: function(oldZoom, newZoom) {
          // Permettre tous les zooms dans les limites définies
          return true;
        },
        onZoom: function(zoom) {
          // Optionnel: callback après zoom
        }
      });
      
      // Observer les changements de taille du conteneur
      if (window.ResizeObserver) {
        const resizeObserver = new ResizeObserver(entries => {
          // Redimensionner et recentrer quand le conteneur change de taille
          requestAnimationFrame(() => {
            if (instance) {
              instance.resize();
              instance.fit();
              instance.center();
            }
          });
        });
        resizeObserver.observe(svgWrapper);
      }
      
      // Créer les boutons personnalisés
      controls.forEach((ctrl, index) => {
        const btn = document.createElement('button');
        btn.innerHTML = ctrl.text;
        btn.title = ctrl.title;
        btn.style.cssText = `
          background: #4CAF50; 
          color: white; 
          border: none; 
          padding: 6px 10px; 
          margin: 0 2px; 
          border-radius: 3px; 
          cursor: pointer; 
          font-size: 14px;
          font-weight: bold;
          transition: background 0.2s;
        `;
        
        // Effets hover
        btn.onmouseover = () => btn.style.background = '#45a049';
        btn.onmouseout = () => btn.style.background = '#4CAF50';
        
        // Actions des boutons
        btn.onclick = () => {
          switch(ctrl.action) {
            case 'zoomIn': instance.zoomIn(); break;
            case 'zoomOut': instance.zoomOut(); break;
            case 'fit': 
              instance.fit(); 
              instance.center(); 
              break;
            case 'reset':
              instance.reset();
              instance.fit();
              instance.center();
              break;
          }
        };
        
        controlsDiv.appendChild(btn);
      });
      
      svg.__pz__ = instance;
      
      // S'ajuster initialement après un court délai
      setTimeout(() => {
        if (instance) {
          instance.fit();
          instance.center();
        }
      }, 100);
      
    } catch(e){ 
      console.warn('svg-pan-zoom enhancement failed:', e);
    }
  }
  
  // Enhance existing svgs
  document.querySelectorAll('svg').forEach(enhance);
  
  // Watch for new ones (Viz.js renders async)
  const obs = new MutationObserver(muts => {
    muts.forEach(m => {
      m.addedNodes && m.addedNodes.forEach(n => {
        if (n instanceof SVGSVGElement) enhance(n);
        if (n.querySelectorAll) n.querySelectorAll('svg').forEach(enhance);
      });
    });
  });
  obs.observe(document.documentElement, { childList: true, subtree: true });
})();