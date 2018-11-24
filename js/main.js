const _CNF = {
    hower_color: null,
    default_lat: 48.1486,
    default_lng: 17.1077,
    default_zoom: 13,
    // mapbox_url: `https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token={accessToken}`,
    mapbox_url: `https://api.mapbox.com/styles/v1/fuxofuxo/cjovl526d5i522sno2vh9t8mq/tiles/{z}/{x}/{y}?access_token={accessToken}#12.0/48.866500/2.317600/0`,
    attribution: `Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>`,
    map: {
        map: null,
        element_id: 'map',
        max_zoom: 22,
        access_token: 'pk.eyJ1IjoiZnV4b2Z1eG8iLCJhIjoiY2pudDIxNG95MHA2cTNxbzQ1NnpsN3NibiJ9.KqE8BWKdXQf0o3VlsMn6Sw',
        type: {
            streets: 'mapbox.streets',
            satelite: 'mapbox.satellite',
            light: 'mapbox.light',
            dark: 'mapbox.dark',
            outdoors: 'mapbox.outdoors'
        },
        base_layers: null,
        search_layer: null,
        my_position_layer: null,
        my_position_distance_layer: null,
        barrier_layer: null
    }
}

$( document ).ready( () => {
    // _CNF.map.base_layers = generateBaseLayers(_CNF.map.type.streets);
    
    // _CNF.map.map = L.map(_CNF.map.element_id, {
    //     center: [_CNF.default_lat, _CNF.default_lng],
    //     zoom: _CNF.default_zoom,
    //     layers: [_CNF.map.base_layers.streets]
    // });

    // L.control.layers(generateBaseLayersComponent()).addTo(_CNF.map.map);

    _CNF.map.map = L.map('map').setView([_CNF.default_lat, _CNF.default_lng], _CNF.default_zoom);

    L.tileLayer(
        'https://api.mapbox.com/styles/v1/fuxofuxo/cjovl526d5i522sno2vh9t8mq/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoiZnV4b2Z1eG8iLCJhIjoiY2pudDIxNG95MHA2cTNxbzQ1NnpsN3NibiJ9.KqE8BWKdXQf0o3VlsMn6Sw', {
        tileSize: 512,
        zoomOffset: -1,
        attribution: '© <a href="https://www.mapbox.com/feedback/">Mapbox</a> © <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(_CNF.map.map);

    _CNF.map.my_position_layer = L.marker([_CNF.default_lat, _CNF.default_lng], {title: "Moja poloha", draggable: true})
    .addTo(_CNF.map.map)
    .on('dragend', () => {
        setMyPositionRange();
        _CNF.map.my_position_layer.bindPopup(`Pozicia: [${getMyPosition()[0]}, ${getMyPosition()[1]}]`);
    }).on('dragstart', () => {
        if(_CNF.map.my_position_distance_layer){
            _CNF.map.my_position_distance_layer.clearLayers();
        }
    });

    setMyPositionRange();

    $('#surfaceSelect').multiselect({
        buttonWidth: '100%',
        enableHTML: true
    });

    $('#nearby').multiselect({
        buttonWidth: '100%'
    });

    $('#rangePicker').on('input', (event) => {
        setMyPositionRange();
    });

    $('#barriers').change(function() {
        if($(this).is(":checked")) {
            const params = {
                surfaceClausule: $('#surfaceSelect').val() ? $('#surfaceSelect').val() : null,
                rangeDistance: parseInt($('#rangePicker').val()) != 0 ? parseInt($('#rangePicker').val()) : null,
                myPosition: getMyPosition(),
                limit: parseInt($('#limit').val()) > 0 ? parseInt($('#limit').val()) : null,
                water: $('#water').is(':checked'),
                longest: $('#longest').is(':checked')
            }
            $.ajax({
                method: "POST",
                url: "./server/RequestManager.php",
                data: { 
                    action: "findBarriers",
                    params: params
                }
                }).done(function (response) {
                    response = JSON.parse(response);
                    if(response.error){
                        console.error(response.error);
                    } else {
                        if(_CNF.map.barrier_layer){
                            _CNF.map.barrier_layer.clearLayers();
                        }
                        var geojsonMarkerOptions = {
                            radius: 4,
                            fillColor: "#ff3300",
                            color: "#3a3a3a",
                            weight: 1,
                            opacity: 1,
                            fillOpacity: 0.8
                        };
                        _CNF.map.barrier_layer = L.geoJSON(response.data, {
                            pointToLayer: function (feature, latlng) {
                                return L.circleMarker(latlng, geojsonMarkerOptions);
                            },
                            onEachFeature: (feature, layer) => {
                                const popupText = `<table border="0">
                                    <tr><td><strong>Nazov bariery:</strong></td><td>${feature.properties.name ? (feature.properties.name == 'bollard' ? 'stlpik' : feature.properties.name) : 'neuvedene'}</td></tr>
                                    </table>`;
                                layer.bindPopup(popupText);
                            }
                        }).addTo(_CNF.map.map);
                    }
                    
                });
        } else {
            if(_CNF.map.barrier_layer){
                _CNF.map.barrier_layer.clearLayers();
            }
        }        
    });

    $('#searchButton').click(event => {
        const params = {
            surfaceClausule: $('#surfaceSelect').val() ? $('#surfaceSelect').val() : null,
            rangeDistance: parseInt($('#rangePicker').val()) != 0 ? parseInt($('#rangePicker').val()) : null,
            myPosition: getMyPosition(),
            limit: parseInt($('#limit').val()) > 0 ? parseInt($('#limit').val()) : null,
            water: $('#water').is(':checked'),
            longest: $('#longest').is(':checked')
        }

        $.ajax({
            method: "POST",
            url: "./server/RequestManager.php",
            data: { 
                action: "search",
                params: params
            }
            })
            .done(function (response) {
                // console.log(response);
                response = JSON.parse(response);
                if(response.error){
                    console.error(response.error);
                } else {
                    if(_CNF.map.search_layer){
                        _CNF.map.search_layer.clearLayers();
                    } 
   
                    _CNF.map.search_layer = L.geoJSON(response.data, {
                        onEachFeature: (feature, layer) => {
                            const popupText = `<table border="0">
                                <tr><td><strong>Nazov:</strong></td><td>${feature.properties.name ? feature.properties.name : 'neuvedene'}</td></tr>
                                <tr><td><strong>Povrch:</strong></td><td>${feature.properties.surface ? colorPalete(feature.properties.surface, true) : 'neuvedene'}</td></tr>
                                <tr><td><strong>Dlzka:</strong></td><td>${feature.properties.length ? parseInt(feature.properties.length) : 'neuvedene'}m</td></tr>
                                </table>`;
                            layer.bindPopup(popupText);
                            layer.on({
                                mouseover: highlightFeature,
                                mouseout: resetHighlight
                            });
                            layer._leaflet_id = feature.properties.id;
                        },
                        style: (feature) => {
                            const color = colorPalete(feature.properties.surface);
                            return {color: color, weight: 4};
                        }
                    }).addTo(_CNF.map.map);
                }
                $('#featureWrapper').empty().append(`<table id="featureWrapperTable" border="1"></table>`);
                $.each(response.data, (key, value) => { 
                    $('#featureWrapperTable').append(`<tr id="${value.properties.id}"><td>${value.properties.name ? value.properties.name : 'Neuvedeny nazov'}</td>
                                                        <td>${parseInt(value.properties.distance)}m</td></tr>`);
                    if(key > 20) {return false;}
                });
                $( "#featureWrapperTable > tr" ).hover((event) => {
                    if(event.type == 'mouseenter'){
                        highlightFeature({target: _CNF.map.search_layer.getLayer($(event.currentTarget).attr('id'))});
                        $(event.currentTarget).addClass('hover');
                    } else if (event.type == 'mouseleave') {
                        resetHighlight({target: _CNF.map.search_layer.getLayer($(event.currentTarget).attr('id'))});
                        $(event.currentTarget).removeClass('hover');
                    }
                });
                $( "#featureWrapperTable > tr" ).off('click').on('click', (event) => {
                    _CNF.map.map.fitBounds(_CNF.map.search_layer.getLayer($(event.currentTarget).attr('id')).getBounds());
                    _CNF.map.map.setZoom(_CNF.default_zoom + 2);
                });
                $('#barriers').trigger('change');
            });
    });
});

function setMyPositionRange(){
    if(_CNF.map.my_position_distance_layer){
        _CNF.map.my_position_distance_layer.clearLayers();
    }
    _CNF.map.my_position_distance_layer = L.featureGroup().addTo(_CNF.map.map);
    L.circle(getMyPosition(), parseInt($('#rangePicker').val())).addTo(_CNF.map.my_position_distance_layer);
}

function getMyPosition(){
    var coord = String(_CNF.map.my_position_layer.getLatLng()).split(',');
    var lat = coord[0].split('(')[1].replace(/\s/g,'');
    var lng = coord[1].split(')')[0].replace(/\s/g,'');
    return [lat, lng];
}

function colorPalete(surface, translate = false){
    switch (surface) {
        case 'asphalt': return translate ? 'Asfalt' : "#000000";
        case 'concrete':   return translate ? 'Beton' : "#283593";
        case 'dirt':   return translate ? 'Neupraveny' : "#FF3D00";
        case 'grass':   return translate ? 'Trava' : "#008000";
        case 'gravel':   return translate ? 'Strk' : "#FFD600";
        case 'ground':   return translate ? 'Zem' : "#FFD600";
        case 'paved':   return translate ? 'Spevneny' : "#9E9D24";
        case 'paving_stones':   return translate ? 'Spevneny kamennou dlazbou' : "#FF5722";
        case 'sett':   return translate ? 'Spevneny kamenom' : "#FFB74D";
        default:   return translate ? 'Nedefinovany' : "#64FFDA";
    }
}

function generateBaseLayers(mapType){
    const response = {};
    Object.keys(_CNF.map.type).forEach(element => {
        response[element] = L.tileLayer(_CNF.mapbox_url, {
            attribution: _CNF.attribution,
            maxZoom: _CNF.map.max_zoom,
            id: _CNF.map.type[element],
            accessToken: _CNF.map.access_token
        });
    });
    return response;
}

function generateBaseLayersComponent(){
    return {
        "Streets": _CNF.map.base_layers.streets,
        "Satelite": _CNF.map.base_layers.satelite,
        "Outdoor": _CNF.map.base_layers.outdoors,
        "Grayscale": _CNF.map.base_layers.light,
        "Dark": _CNF.map.base_layers.dark
    };
}

function highlightFeature(e) {
    var layer = e.target;
    _CNF.hower_color = layer.options.color;
    layer.setStyle({
      color: '#ff3300',
      weight: 12,
      opacity: 0.6
    });

  }
  
  function resetHighlight(e) {
    var layer = e.target;
    layer.setStyle({
      color: _CNF.hower_color,
      weight: 4,
      opacity: 1.0
    });
  }