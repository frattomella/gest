(function (blocks, element, components, serverSideRender) {
  const el = element.createElement;
  const InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
  const useBlockProps = wp.blockEditor ? wp.blockEditor.useBlockProps : function(){ return {}; };
  const PanelBody = components.PanelBody;
  const RangeControl = components.RangeControl;
  const SelectControl = components.SelectControl;
  const TextControl = components.TextControl;
  const ToggleControl = components.ToggleControl;
  const Notice = components.Notice;
  const ColorPalette = components.ColorPalette;
  const BaseControl = components.BaseControl;
  const Disabled = components.Disabled || function (props) {
    return el('div', { style: { pointerEvents: 'none' } }, props.children);
  };
  const ServerSideRender = serverSideRender;
  const useEffect = element.useEffect;
  const useRef = element.useRef;
  const blockData = window.gpoBlockData || { catalogPages: [] };

  const FILTER_OPTIONS = [
    ['search', 'Ricerca testuale'],
    ['condition', 'Condizione'],
    ['brand', 'Marca'],
    ['fuel', 'Alimentazione'],
    ['body_type', 'Carrozzeria'],
    ['transmission', 'Cambio'],
    ['year', 'Anno'],
    ['min_price', 'Prezzo minimo'],
    ['max_price', 'Prezzo massimo'],
    ['max_mileage', 'Chilometri massimi'],
    ['sort', 'Ordinamento']
  ];

  const CARD_OPTIONS = [
    ['image', 'Immagine'],
    ['badge', 'Badge'],
    ['brand', 'Marca / modello'],
    ['title', 'Titolo'],
    ['price', 'Prezzo'],
    ['chips', 'Badge info rapide'],
    ['year', 'Anno'],
    ['mileage', 'Chilometraggio'],
    ['body_type', 'Carrozzeria'],
    ['transmission', 'Cambio'],
    ['engine_size', 'Cilindrata'],
    ['specs', 'Specifiche sintetiche'],
    ['primary_button', 'Bottone principale'],
    ['secondary_button', 'Link secondario']
  ];

  const COLOR_PRESETS = [
    { name: 'Blu GestPark', color: '#113a7d' },
    { name: 'Verde GestPark', color: '#37a83a' },
    { name: 'Blu profondo', color: '#0f172a' },
    { name: 'Blu accento', color: '#1d4ed8' },
    { name: 'Bianco', color: '#ffffff' },
    { name: 'Grigio chiaro', color: '#f8fafc' },
    { name: 'Blu testo', color: '#0b214a' }
  ];

  function parseCsv(value) {
    return String(value || '').split(',').map(function(item){ return item.trim(); }).filter(Boolean);
  }

  function serializeCsv(items) {
    return items.filter(Boolean).join(',');
  }

  function toggleCsvValue(value, key, enabled) {
    const items = parseCsv(value);
    const next = items.filter(function(item){ return item !== key; });
    if (enabled) {
      next.push(key);
    }
    return serializeCsv(next);
  }

  function currentCssVar(name, fallback) {
    const root = window.getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return root || fallback || '';
  }

  function resolvedCsvValue(props, attribute, fallbackAttribute) {
    var direct = String(props.attributes[attribute] || '').trim();
    if (direct) {
      return direct;
    }
    if (fallbackAttribute) {
      return String(props.attributes[fallbackAttribute] || '');
    }
    return direct;
  }

  function disablePreviewInteractions(root) {
    if (!root) {
      return;
    }

    root.querySelectorAll('img, a, button, input, select, textarea').forEach(function (node) {
      node.setAttribute('draggable', 'false');

      if (/^(A|BUTTON|INPUT|SELECT|TEXTAREA)$/.test(node.tagName)) {
        node.setAttribute('tabindex', '-1');
      }
    });
  }

  function ensureDefaultColors(props) {
    useEffect(function () {
      const updates = {};
      if (!props.attributes.primaryColor) updates.primaryColor = currentCssVar('--gpo-primary', '#113a7d');
      if (!props.attributes.accentColor) updates.accentColor = currentCssVar('--gpo-accent', '#37a83a');
      if (!props.attributes.bgColor) updates.bgColor = currentCssVar('--gpo-bg', '#ffffff');
      if (!props.attributes.textColor) updates.textColor = currentCssVar('--gpo-primary', '#113a7d');
      if (!props.attributes.buttonColor) updates.buttonColor = currentCssVar('--gpo-primary', '#113a7d');
      if (!props.attributes.buttonTextColor) updates.buttonTextColor = '#ffffff';
      if (Object.keys(updates).length) {
        props.setAttributes(updates);
      }
    }, []);
  }

  function previewEdit(props, title, description, controls) {
    const blockProps = useBlockProps({ className: 'gpo-block-preview' });
    const frameRef = useRef(null);
    ensureDefaultColors(props);
    useEffect(function () {
      const root = frameRef.current;
      if (!root) {
        return;
      }

      disablePreviewInteractions(root);

      const observer = new window.MutationObserver(function () {
        disablePreviewInteractions(root);
      });

      observer.observe(root, { childList: true, subtree: true });

      return function () {
        observer.disconnect();
      };
    }, [props.attributes]);
    return el('div', blockProps, [
      el(InspectorControls, {}, controls || []),
      el('div', { className: 'gpo-block-label' }, title),
      description ? el('p', { style: { marginTop: 0, color: '#475569' } }, description) : null,
      el('div', { className: 'gpo-editor-frame', ref: frameRef }, [
        el(Disabled, {}, el(ServerSideRender, {
          block: props.name,
          attributes: props.attributes,
          EmptyResponsePlaceholder: function(){
            return el(Notice, { status: 'warning', isDismissible: false }, 'Nessuna anteprima disponibile. Importa almeno un veicolo reale oppure controlla il blocco.');
          },
          ErrorResponsePlaceholder: function(obj){
            return el(Notice, { status: 'error', isDismissible: false }, "Errore nell'anteprima del blocco GestPark. " + (obj && obj.response && obj.response.errorMsg ? obj.response.errorMsg : 'Controlla la configurazione del plugin.'));
          }
        }))
      ])
    ]);
  }

  function panel(title, controls) {
    return [el(PanelBody, { title: title || 'Impostazioni blocco', initialOpen: true }, controls)];
  }

  function colorControl(props, attribute, label, fallbackCssVar, fallbackColor) {
    const value = props.attributes[attribute] || currentCssVar(fallbackCssVar, fallbackColor);
    return el(BaseControl, { label: label }, el(ColorPalette, {
      colors: COLOR_PRESETS,
      value: value,
      onChange: function (next) { props.setAttributes((function(){ const o={}; o[attribute]=next || fallbackColor; return o; })()); },
      disableCustomColors: false,
      clearable: false
    }));
  }

  function styleControls(props) {
    return [
      colorControl(props, 'primaryColor', 'Colore principale', '--gpo-primary', '#113a7d'),
      colorControl(props, 'accentColor', 'Colore accento', '--gpo-accent', '#37a83a'),
      colorControl(props, 'bgColor', 'Sfondo card/sezione', '--gpo-bg', '#ffffff'),
      colorControl(props, 'textColor', 'Colore testo', '--gpo-primary', '#113a7d'),
      colorControl(props, 'buttonColor', 'Colore pulsante', '--gpo-primary', '#113a7d'),
      colorControl(props, 'buttonTextColor', 'Colore testo pulsante', '--gpo-button-text', '#ffffff')
    ];
  }

  function toggleGroup(props, title, attribute, options, fallbackAttribute) {
    return el(PanelBody, { title: title, initialOpen: false }, options.map(function (option) {
      const key = option[0];
      const label = option[1];
      return el(ToggleControl, {
        label: label,
        checked: parseCsv(resolvedCsvValue(props, attribute, fallbackAttribute)).indexOf(key) !== -1,
        onChange: function (enabled) {
          const update = {};
          update[attribute] = toggleCsvValue(resolvedCsvValue(props, attribute, fallbackAttribute), key, enabled);
          props.setAttributes(update);
        }
      });
    }));
  }

  function deviceToggleGroups(props) {
    return [
      toggleGroup(props, 'Campi visibili desktop', 'showDesktop', CARD_OPTIONS, 'show'),
      toggleGroup(props, 'Campi visibili tablet', 'showTablet', CARD_OPTIONS, 'show'),
      toggleGroup(props, 'Campi visibili mobile', 'showMobile', CARD_OPTIONS, 'show')
    ];
  }

  function catalogInspector(props, includeFilters) {
    return [
      el(PanelBody, { title: 'Impostazioni catalogo', initialOpen: true }, [
        el(RangeControl, {
          label: 'Numero elementi',
          value: props.attributes.limit,
          min: 1,
          max: 24,
          onChange: function (value) { props.setAttributes({ limit: value || 1 }); }
        }),
        el(SelectControl, {
          label: 'Colonne',
          value: props.attributes.columns,
          options: [{label:'2', value:2},{label:'3', value:3},{label:'4', value:4}],
          onChange: function (value) { props.setAttributes({ columns: parseInt(value,10) }); }
        }),
        el(SelectControl, {
          label: 'Layout card',
          value: props.attributes.cardLayout,
          options: [{label:'Default', value:'default'},{label:'Compact', value:'compact'},{label:'Minimal', value:'minimal'}],
          onChange: function (value) { props.setAttributes({ cardLayout: value }); }
        }),
        el(RangeControl, {
          label: 'Padding laterale contenitore',
          value: props.attributes.outerPaddingX,
          min: 0,
          max: 80,
          onChange: function (value) { props.setAttributes({ outerPaddingX: value || 0 }); }
        }),
        el(RangeControl, {
          label: 'Spazio verticale tra moduli',
          value: props.attributes.sectionGap,
          min: 0,
          max: 80,
          onChange: function (value) { props.setAttributes({ sectionGap: value || 0 }); }
        }),
        el(TextControl, { label: 'Testo bottone principale', value: props.attributes.primaryButtonLabel || 'Scheda veicolo', onChange: function (value) { props.setAttributes({ primaryButtonLabel: value }); } }),
        el(TextControl, { label: 'Testo link secondario', value: props.attributes.secondaryButtonLabel || 'Richiedi info', onChange: function (value) { props.setAttributes({ secondaryButtonLabel: value }); } })
      ].concat(styleControls(props)))
    ].concat(deviceToggleGroups(props)).concat(includeFilters ? [toggleGroup(props, 'Filtri visibili', 'filterFields', FILTER_OPTIONS)] : []);
  }

  function cardDisplayInspector(props) {
    return [
      el(PanelBody, { title: 'Impostazioni card', initialOpen: true }, [
        el(SelectControl, {
          label: 'Layout card',
          value: props.attributes.cardLayout,
          options: [{label:'Default', value:'default'},{label:'Compact', value:'compact'},{label:'Minimal', value:'minimal'}],
          onChange: function (value) { props.setAttributes({ cardLayout: value }); }
        }),
        el(RangeControl, {
          label: 'Padding laterale contenitore',
          value: props.attributes.outerPaddingX,
          min: 0,
          max: 80,
          onChange: function (value) { props.setAttributes({ outerPaddingX: value || 0 }); }
        }),
        el(RangeControl, {
          label: 'Spazio verticale tra moduli',
          value: props.attributes.sectionGap,
          min: 0,
          max: 80,
          onChange: function (value) { props.setAttributes({ sectionGap: value || 0 }); }
        }),
        el(TextControl, { label: 'Testo bottone principale', value: props.attributes.primaryButtonLabel || 'Scheda veicolo', onChange: function (value) { props.setAttributes({ primaryButtonLabel: value }); } }),
        el(TextControl, { label: 'Testo link secondario', value: props.attributes.secondaryButtonLabel || 'Richiedi info', onChange: function (value) { props.setAttributes({ secondaryButtonLabel: value }); } })
      ].concat(styleControls(props)))
    ].concat(deviceToggleGroups(props));
  }

  blocks.registerBlockType('gestpark/vehicle-grid', {
    title: 'GestPark Griglia veicoli',
    icon: 'screenoptions',
    category: 'widgets',
    attributes: { limit:{type:'number',default:6}, columns:{type:'number',default:3}, cardLayout:{type:'string',default:'default'}, show:{type:'string',default:'image,badge,brand,title,price,chips,year,mileage,body_type,transmission,engine_size,primary_button,secondary_button'}, showDesktop:{type:'string',default:''}, showTablet:{type:'string',default:''}, showMobile:{type:'string',default:''}, filterFields:{type:'string',default:'search,condition,brand,fuel,body_type,transmission,year,min_price,max_price,max_mileage,sort'}, outerPaddingX:{type:'number',default:18}, sectionGap:{type:'number',default:24}, primaryColor:{type:'string',default:''}, accentColor:{type:'string',default:''}, bgColor:{type:'string',default:''}, textColor:{type:'string',default:''}, buttonColor:{type:'string',default:''}, buttonTextColor:{type:'string',default:''}, primaryButtonLabel:{type:'string',default:'Scheda veicolo'}, secondaryButtonLabel:{type:'string',default:'Richiedi info'} },
    edit: function (props) { return previewEdit(props, 'GestPark Griglia veicoli', 'Anteprima reale della griglia responsive del catalogo.', catalogInspector(props, true)); },
    save: function () { return null; }
  });

  blocks.registerBlockType('gestpark/vehicle-catalog', {
    title: 'GestPark Catalogo veicoli',
    icon: 'car',
    category: 'widgets',
    attributes: { limit:{type:'number',default:12}, columns:{type:'number',default:3}, cardLayout:{type:'string',default:'default'}, show:{type:'string',default:'image,badge,brand,title,price,chips,year,mileage,body_type,transmission,engine_size,primary_button,secondary_button'}, showDesktop:{type:'string',default:''}, showTablet:{type:'string',default:''}, showMobile:{type:'string',default:''}, filterFields:{type:'string',default:'search,condition,brand,fuel,body_type,transmission,year,min_price,max_price,max_mileage,sort'}, outerPaddingX:{type:'number',default:18}, sectionGap:{type:'number',default:24}, primaryColor:{type:'string',default:''}, accentColor:{type:'string',default:''}, bgColor:{type:'string',default:''}, textColor:{type:'string',default:''}, buttonColor:{type:'string',default:''}, buttonTextColor:{type:'string',default:''}, primaryButtonLabel:{type:'string',default:'Scheda veicolo'}, secondaryButtonLabel:{type:'string',default:'Richiedi info'} },
    edit: function (props) { return previewEdit(props, 'GestPark Catalogo veicoli', 'Anteprima reale del catalogo con filtri personalizzabili.', catalogInspector(props, true)); },
    save: function () { return null; }
  });

  blocks.registerBlockType('gestpark/featured-carousel', {
    title: 'GestPark Carosello vetrina',
    icon: 'images-alt2',
    category: 'widgets',
    attributes: { show:{type:'string',default:'image,badge,brand,title,price,chips,primary_button,secondary_button'}, showDesktop:{type:'string',default:''}, showTablet:{type:'string',default:''}, showMobile:{type:'string',default:''}, cardLayout:{type:'string',default:'default'}, autoplay:{type:'boolean',default:true}, interval:{type:'number',default:5000}, itemsPerPage:{type:'number',default:3}, showTitle:{type:'boolean',default:true}, sectionTitle:{type:'string',default:'Veicoli selezionati'}, outerPaddingX:{type:'number',default:18}, sectionGap:{type:'number',default:24}, primaryColor:{type:'string',default:''}, accentColor:{type:'string',default:''}, bgColor:{type:'string',default:''}, textColor:{type:'string',default:''}, buttonColor:{type:'string',default:''}, buttonTextColor:{type:'string',default:''}, primaryButtonLabel:{type:'string',default:'Scheda veicolo'}, secondaryButtonLabel:{type:'string',default:'Richiedi info'} },
    edit: function (props) {
      var controls = cardDisplayInspector(props);
      controls.unshift(
        el(PanelBody, { title:'Impostazioni carosello', initialOpen:true }, [
          el(ToggleControl, { label:'Mostra titolo sezione', checked:props.attributes.showTitle !== false, onChange:function(v){ props.setAttributes({ showTitle:v }); } }),
          props.attributes.showTitle !== false ? el(TextControl, { label:'Titolo sezione', value:props.attributes.sectionTitle || 'Veicoli selezionati', onChange:function(v){ props.setAttributes({ sectionTitle:v }); } }) : null,
          el(RangeControl, { label:'Veicoli visibili per pagina', value:props.attributes.itemsPerPage, min:1, max:4, onChange:function(v){ props.setAttributes({ itemsPerPage:v || 1 }); } }),
          el(ToggleControl, { label:'Scorrimento automatico', checked:props.attributes.autoplay, onChange:function(v){ props.setAttributes({ autoplay:v }); } }),
          el(RangeControl, { label:'Intervallo autoplay', value:props.attributes.interval, min:1500, max:10000, step:100, onChange:function(v){ props.setAttributes({ interval:v || 1500 }); } })
        ])
      );
      return previewEdit(props, 'GestPark Carosello vetrina', 'Anteprima reale del carosello dei veicoli in vetrina.', controls);
    },
    save: function () { return null; }
  });

  blocks.registerBlockType('gestpark/featured-vehicle', {
    title: 'GestPark Veicolo in evidenza',
    icon: 'star-filled',
    category: 'widgets',
    attributes: { show:{type:'string',default:'image,badge,brand,title,price,chips,year,mileage,body_type,transmission,engine_size,primary_button,secondary_button'}, showDesktop:{type:'string',default:''}, showTablet:{type:'string',default:''}, showMobile:{type:'string',default:''}, cardLayout:{type:'string',default:'default'}, outerPaddingX:{type:'number',default:18}, sectionGap:{type:'number',default:24}, primaryColor:{type:'string',default:''}, accentColor:{type:'string',default:''}, bgColor:{type:'string',default:''}, textColor:{type:'string',default:''}, buttonColor:{type:'string',default:''}, buttonTextColor:{type:'string',default:''}, primaryButtonLabel:{type:'string',default:'Scheda veicolo'}, secondaryButtonLabel:{type:'string',default:'Richiedi info'} },
    edit: function (props) { return previewEdit(props, 'GestPark Veicolo in evidenza', 'Anteprima reale del veicolo in evidenza.', cardDisplayInspector(props)); },
    save: function () { return null; }
  });

  blocks.registerBlockType('gestpark/vehicle-hero', {
    title: 'GestPark Hero veicolo',
    icon: 'cover-image',
    category: 'widgets',
    attributes: { showImage:{type:'boolean',default:true}, showMeta:{type:'boolean',default:true}, showChips:{type:'boolean',default:true}, showLeadForm:{type:'boolean',default:false}, primaryColor:{type:'string',default:''}, accentColor:{type:'string',default:''}, bgColor:{type:'string',default:''}, textColor:{type:'string',default:''}, buttonColor:{type:'string',default:''}, buttonTextColor:{type:'string',default:''} },
    edit: function (props) {
      return previewEdit(props, 'GestPark Hero veicolo', 'Blocco principale della scheda veicolo con immagine, prezzo e riepilogo.', panel('Impostazioni hero', [
        el(ToggleControl, { label:'Mostra immagine', checked:props.attributes.showImage, onChange:function(v){props.setAttributes({showImage:v});} }),
        el(ToggleControl, { label:'Mostra specifiche rapide', checked:props.attributes.showMeta, onChange:function(v){props.setAttributes({showMeta:v});} }),
        el(ToggleControl, { label:'Mostra chip rapidi', checked:props.attributes.showChips, onChange:function(v){props.setAttributes({showChips:v});} }),
        el(ToggleControl, { label:'Mostra modulo richieste nella card', checked:props.attributes.showLeadForm, onChange:function(v){props.setAttributes({showLeadForm:v});} })
      ].concat(styleControls(props))));
    },
    save: function(){ return null; }
  });

  blocks.registerBlockType('gestpark/vehicle-gallery', {
    title: 'GestPark Galleria veicolo',
    icon: 'format-gallery',
    category: 'widgets',
    attributes: {},
    edit: function (props) { return previewEdit(props, 'GestPark Galleria veicolo', 'Galleria fotografica dinamica del veicolo corrente.', []); },
    save: function(){ return null; }
  });

  blocks.registerBlockType('gestpark/vehicle-specs', {
    title: 'GestPark Scheda tecnica',
    icon: 'feedback',
    category: 'widgets',
    attributes: { fields:{type:'string',default:'condition,year,fuel,mileage,body_type,transmission,engine_size,power,color,doors,seats,location'}, layout:{type:'string',default:'grid'}, title:{type:'string',default:'Scheda tecnica'}, primaryColor:{type:'string',default:''}, accentColor:{type:'string',default:''}, bgColor:{type:'string',default:''}, textColor:{type:'string',default:''} },
    edit: function (props) {
      return previewEdit(props, 'GestPark Scheda tecnica', 'Anteprima reale delle specifiche del veicolo.', [
        el(PanelBody, { title:'Impostazioni scheda tecnica', initialOpen:true }, [
          el(TextControl, { label:'Titolo sezione', value:props.attributes.title, onChange:function(v){props.setAttributes({title:v});} }),
          el(SelectControl, { label:'Layout', value:props.attributes.layout, options:[{label:'Griglia', value:'grid'},{label:'Tabella', value:'table'}], onChange:function(v){props.setAttributes({layout:v});} })
        ].concat(styleControls(props))),
        toggleGroup(props, 'Campi visibili', 'fields', [
          ['condition','Condizione'],['year','Anno'],['fuel','Alimentazione'],['mileage','Chilometraggio'],['body_type','Carrozzeria'],['transmission','Cambio'],['engine_size','Cilindrata'],['power','Potenza'],['color','Colore'],['doors','Porte'],['seats','Posti'],['location','Sede']
        ])
      ]);
    },
    save: function(){ return null; }
  });

  ['vehicle-description','vehicle-notes','vehicle-accessories'].forEach(function(name){
    const labels = {
      'vehicle-description':'Descrizione',
      'vehicle-notes':'Note',
      'vehicle-accessories':'Accessori'
    };
    blocks.registerBlockType('gestpark/' + name, {
      title: 'GestPark ' + labels[name],
      icon: 'excerpt-view',
      category: 'widgets',
      attributes: { title:{type:'string',default:labels[name]}, collapsedByDefault:{type:'boolean',default:name === 'vehicle-accessories'}, primaryColor:{type:'string',default:''}, accentColor:{type:'string',default:''}, bgColor:{type:'string',default:''}, textColor:{type:'string',default:''} },
      edit: function (props) {
        return previewEdit(props, 'GestPark ' + labels[name], 'Anteprima reale della sezione dinamica del veicolo corrente.', panel('Impostazioni sezione', [
          el(TextControl, { label:'Titolo sezione', value:props.attributes.title, onChange:function(v){props.setAttributes({title:v});} }),
          name === 'vehicle-accessories' ? el(ToggleControl, { label:'Chiudi di default', checked:props.attributes.collapsedByDefault, onChange:function(v){props.setAttributes({collapsedByDefault:v});} }) : null
        ].concat(styleControls(props))));
      },
      save: function(){ return null; }
    });
  });

  blocks.registerBlockType('gestpark/vehicle-contact', {
    title: 'GestPark Box contatto',
    icon: 'email-alt',
    category: 'widgets',
    attributes: { title:{type:'string',default:'Richiedi informazioni'}, text:{type:'string',default:'Compila il modulo per ricevere disponibilita, prova su strada e proposta commerciale su questo veicolo.'}, buttonLabel:{type:'string',default:'Invia richiesta'}, primaryColor:{type:'string',default:''}, accentColor:{type:'string',default:''}, bgColor:{type:'string',default:''}, textColor:{type:'string',default:''}, buttonColor:{type:'string',default:''}, buttonTextColor:{type:'string',default:''} },
    edit: function (props) {
      return previewEdit(props, 'GestPark Box contatto', 'Anteprima reale del modulo richieste collegato all\'email impostata nel plugin.', panel('Impostazioni box contatto', [
        el(TextControl, { label:'Titolo', value:props.attributes.title, onChange:function(v){props.setAttributes({title:v});} }),
        el(TextControl, { label:'Testo', value:props.attributes.text, onChange:function(v){props.setAttributes({text:v});} }),
        el(TextControl, { label:'Etichetta pulsante', value:props.attributes.buttonLabel, onChange:function(v){props.setAttributes({buttonLabel:v});} })
      ].concat(styleControls(props))));
    },
    save: function(){ return null; }
  });

  blocks.registerBlockType('gestpark/vehicle-carousel', {
    title: 'GestPark Carosello veicoli',
    icon: 'images-alt2',
    category: 'widgets',
    attributes: { title:{type:'string',default:'Altri veicoli da vedere'}, source:{type:'string',default:'related_brand'}, limit:{type:'number',default:6}, show:{type:'string',default:'image,title,price,primary_button'}, showDesktop:{type:'string',default:''}, showTablet:{type:'string',default:''}, showMobile:{type:'string',default:''}, cardLayout:{type:'string',default:'default'}, primaryColor:{type:'string',default:''}, accentColor:{type:'string',default:''}, bgColor:{type:'string',default:''}, textColor:{type:'string',default:''}, buttonColor:{type:'string',default:''}, buttonTextColor:{type:'string',default:''}, primaryButtonLabel:{type:'string',default:'Scheda veicolo'}, secondaryButtonLabel:{type:'string',default:'Richiedi info'} },
    edit: function (props) {
      return previewEdit(props, 'GestPark Carosello veicoli', 'Anteprima reale del carosello inserito dentro il template veicolo.', [
        el(PanelBody, { title:'Impostazioni carosello', initialOpen:true }, [
          el(TextControl, { label:'Titolo sezione', value:props.attributes.title, onChange:function(v){props.setAttributes({title:v});} }),
          el(SelectControl, { label:'Sorgente', value:props.attributes.source, options:[{label:'Stessa marca', value:'related_brand'},{label:'Veicoli in vetrina', value:'featured'}], onChange:function(v){props.setAttributes({source:v});} }),
          el(RangeControl, { label:'Numero veicoli', value:props.attributes.limit, min:1, max:12, onChange:function(v){props.setAttributes({limit:v || 1});} }),
          el(SelectControl, { label:'Layout card', value:props.attributes.cardLayout, options:[{label:'Default', value:'default'},{label:'Compact', value:'compact'},{label:'Minimal', value:'minimal'}], onChange:function(v){props.setAttributes({cardLayout:v});} }),
          el(TextControl, { label:'Testo bottone principale', value:props.attributes.primaryButtonLabel || 'Scheda veicolo', onChange:function(v){props.setAttributes({primaryButtonLabel:v});} }),
          el(TextControl, { label:'Testo link secondario', value:props.attributes.secondaryButtonLabel || 'Richiedi info', onChange:function(v){props.setAttributes({secondaryButtonLabel:v});} })
        ].concat(styleControls(props))),
      ].concat(deviceToggleGroups(props)));
    },
    save: function(){ return null; }
  });


  function getPageOptions() {
    var opts = [{ label: 'Seleziona una pagina catalogo', value: 0 }];
    (blockData.catalogPages || []).forEach(function(page){
      opts.push({ label: page.title, value: page.id });
    });
    return opts;
  }

  function getCatalogOptions(pageId) {
    var page = (blockData.catalogPages || []).find(function(item){ return Number(item.id) === Number(pageId); });
    if (!page || !page.catalogs) {
      return [{ label: 'Nessun catalogo disponibile', value: 'default' }];
    }
    return page.catalogs.map(function(cat){ return { label: cat.label, value: cat.value }; });
  }

  function targetPageControls(props) {
    var pageOptions = getPageOptions();
    var catalogOptions = getCatalogOptions(props.attributes.pageId);
    return [
      el(SelectControl, { label:'Pagina catalogo', value:props.attributes.pageId || 0, options:pageOptions, onChange:function(v){ props.setAttributes({ pageId: parseInt(v, 10) || 0, catalogRef: (getCatalogOptions(parseInt(v,10)||0)[0] || {}).value || 'default' }); } }),
      el(SelectControl, { label:'Catalogo della pagina', value:props.attributes.catalogRef || 'default', options:catalogOptions, onChange:function(v){ props.setAttributes({ catalogRef:v }); }, disabled: !(props.attributes.pageId > 0) })
    ];
  }

  blocks.registerBlockType('gestpark/brand-carousel', {
    title: 'GestPark Banner marchi',
    icon: 'slides',
    category: 'widgets',
    attributes: { pageId:{type:'number',default:0}, catalogRef:{type:'string',default:'default'}, logoSize:{type:'number',default:96}, cardSize:{type:'number',default:168}, autoplay:{type:'boolean',default:true}, interval:{type:'number',default:6500}, speed:{type:'number',default:900}, primaryColor:{type:'string',default:''}, accentColor:{type:'string',default:''}, bgColor:{type:'string',default:''}, textColor:{type:'string',default:''} },
    edit: function (props) {
      return previewEdit(props, 'GestPark Banner marchi', 'Anteprima reale del marquee continuo dei marchi veicolo.', [
        el(PanelBody, { title:'Impostazioni banner marchi', initialOpen:true }, targetPageControls(props).concat([
          el(RangeControl, { label:'Dimensione card marchio', value:props.attributes.cardSize, min:120, max:280, onChange:function(v){ props.setAttributes({ cardSize:v || 120 }); } }),
          el(RangeControl, { label:'Grandezza logo marchio', value:props.attributes.logoSize, min:56, max:180, onChange:function(v){ props.setAttributes({ logoSize:v || 56 }); } }),
          el(ToggleControl, { label:'Marquee automatico continuo', checked:props.attributes.autoplay, onChange:function(v){ props.setAttributes({ autoplay:v }); } }),
          el(RangeControl, { label:'Durata ciclo marquee', value:props.attributes.interval, min:1500, max:12000, step:100, onChange:function(v){ props.setAttributes({ interval:v || 1500 }); } }),
          el(RangeControl, { label:'Morbidezza animazione', value:props.attributes.speed, min:300, max:3200, step:50, onChange:function(v){ props.setAttributes({ speed:v || 300 }); } })
        ].concat(styleControls(props))))
      ]);
    },
    save: function(){ return null; }
  });

  blocks.registerBlockType('gestpark/vehicle-search', {
    title: 'GestPark Ricerca veicoli',
    icon: 'search',
    category: 'widgets',
    attributes: { pageId:{type:'number',default:0}, catalogRef:{type:'string',default:'default'}, placeholder:{type:'string',default:'Cerca veicolo'}, width:{type:'number',default:100}, radius:{type:'number',default:999}, primaryColor:{type:'string',default:''}, accentColor:{type:'string',default:''}, bgColor:{type:'string',default:''}, textColor:{type:'string',default:''}, buttonColor:{type:'string',default:''} },
    edit: function (props) {
      return previewEdit(props, 'GestPark Ricerca veicoli', '', [
        el(PanelBody, { title:'Impostazioni barra di ricerca', initialOpen:true }, targetPageControls(props).concat([
          el(TextControl, { label:'Placeholder', value:props.attributes.placeholder || 'Cerca veicolo', onChange:function(v){ props.setAttributes({ placeholder:v }); } }),
          el(RangeControl, { label:'Larghezza percentuale', value:props.attributes.width, min:20, max:100, onChange:function(v){ props.setAttributes({ width:v || 20 }); } }),
          el(RangeControl, { label:'Raggio angoli', value:props.attributes.radius, min:36, max:999, onChange:function(v){ props.setAttributes({ radius:v || 36 }); } })
        ].concat(styleControls(props))))
      ]);
    },
    save: function(){ return null; }
  });

})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.serverSideRender);
