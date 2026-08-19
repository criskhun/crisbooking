(() => {
    const defaultCenter = {lat: 12.8797, lng: 121.7740};

    const mapsAvailable = () => Boolean(window.google?.maps?.Map);
    const numberValue = (input) => {
        if (!input || input.value.trim() === '') return null;
        const value = Number(input?.value);
        return Number.isFinite(value) ? value : null;
    };
    const setStatus = (container, message) => {
        if (container.dataset.mapAuthFailed === '1' && !message.startsWith('Google Maps could not authenticate')) return;
        const status = container.querySelector('[data-map-status]');
        if (status) status.textContent = message;
    };
    const showMapsAuthFailure = () => {
        document.querySelectorAll('[data-listing-location-map], [data-search-location-map], [data-overview-nearby-map]').forEach((container) => {
            container.dataset.mapAuthFailed = '1';
            setStatus(container, 'Google Maps could not authenticate. Enable billing and confirm this site is allowed by the API key.');
        });
    };
    const watchForMapsAuthFailure = (container) => {
        const canvas = container.querySelector('[data-map-canvas]');
        if (!canvas) return;
        const detectFailure = () => {
            if (!canvas.textContent.includes("This page can't load Google Maps correctly.")) return false;
            showMapsAuthFailure();
            return true;
        };
        if (detectFailure()) return;
        const observer = new MutationObserver(() => {
            if (detectFailure()) observer.disconnect();
        });
        observer.observe(canvas, {childList: true, subtree: true, characterData: true});
        window.setTimeout(() => observer.disconnect(), 10000);
    };
    const mapOptions = (container, center, zoom) => {
        const options = {center, zoom, streetViewControl: false, mapTypeControl: false, fullscreenControl: true};
        if (container.dataset.mapId) options.mapId = container.dataset.mapId;
        return options;
    };
    const readUnits = (container) => {
        try {
            return JSON.parse(container.querySelector('[data-map-units]')?.textContent || '[]');
        } catch (error) {
            return [];
        }
    };
    const coordinateText = (position) => `${position.lat.toFixed(5)}, ${position.lng.toFixed(5)}`;
    const geolocate = (container, callback) => {
        if (!navigator.geolocation) {
            setStatus(container, 'Location access is not supported by this browser.');
            return;
        }

        setStatus(container, 'Finding your current location…');
        navigator.geolocation.getCurrentPosition(
            ({coords}) => callback({lat: coords.latitude, lng: coords.longitude}),
            () => setStatus(container, 'Location permission was not granted. You can click the map instead.'),
            {enableHighAccuracy: true, timeout: 10000, maximumAge: 60000},
        );
    };
    const reverseGeocode = (geocoder, position, addressInput) => {
        if (!addressInput) return;
        geocoder.geocode({location: position}, (results, status) => {
            if (status === 'OK' && results?.[0]) {
                addressInput.value = results[0].formatted_address;
                addressInput.dispatchEvent(new Event('input', {bubbles: true}));
            }
        });
    };
    const geocodeAddress = (container, geocoder, addressInput, callback) => {
        const address = addressInput?.value.trim();
        if (!address) {
            setStatus(container, 'Type a location or address first.');
            return;
        }

        setStatus(container, 'Finding that location…');
        geocoder.geocode({address, region: 'PH'}, (results, status) => {
            if (status !== 'OK' || !results?.[0]) {
                setStatus(container, 'That location could not be found. Try a more specific address.');
                return;
            }

            const location = results[0].geometry.location;
            addressInput.value = results[0].formatted_address;
            addressInput.dispatchEvent(new Event('input', {bubbles: true}));
            callback({lat: location.lat(), lng: location.lng()});
        });
    };
    const enableAddressSearch = (container, map, geocoder, addressInput, callback) => {
        if (!addressInput) return;

        addressInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            geocodeAddress(container, geocoder, addressInput, callback);
        });

        if (!google.maps.places?.Autocomplete) {
            return;
        }

        const autocomplete = new google.maps.places.Autocomplete(addressInput, {
            componentRestrictions: {country: 'ph'},
            fields: ['formatted_address', 'geometry', 'name'],
        });
        autocomplete.bindTo('bounds', map);
        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            const location = place.geometry?.location;
            if (!location) {
                geocodeAddress(container, geocoder, addressInput, callback);
                return;
            }

            addressInput.value = place.formatted_address || place.name || addressInput.value;
            addressInput.dispatchEvent(new Event('input', {bubbles: true}));
            callback({lat: location.lat(), lng: location.lng()});
            setStatus(container, 'Place selected. Drag the pin if you need a more exact location.');
        });
    };
    const addUnitMarkers = (map, units) => {
        const bounds = new google.maps.LatLngBounds();
        const info = new google.maps.InfoWindow();
        const mappedUnits = units.map((unit) => ({
            ...unit,
            position: {lat: Number(unit.latitude), lng: Number(unit.longitude)},
        })).filter((unit) => Number.isFinite(unit.position.lat) && Number.isFinite(unit.position.lng));
        mappedUnits.forEach((unit) => bounds.extend(unit.position));

        class MapBadgeOverlay extends google.maps.OverlayView {
            constructor(position, element, offset = {x: 0, y: 0}) {
                super();
                this.position = position;
                this.element = element;
                this.offset = offset;
            }

            onAdd() {
                this.getPanes().overlayMouseTarget.append(this.element);
            }

            draw() {
                const pixel = this.getProjection().fromLatLngToDivPixel(this.position);
                if (!pixel) return;
                this.element.style.left = `${pixel.x + this.offset.x}px`;
                this.element.style.top = `${pixel.y + this.offset.y}px`;
            }

            onRemove() {
                this.element.remove();
            }
        }

        const initialsFor = (unit) => (unit.business_name || unit.host_name || 'Host')
            .split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
        const priceFor = (unit) => {
            const price = Number(unit.starting_price);
            if (!Number.isFinite(price)) return null;

            return `₱${price.toLocaleString('en-PH', {
                minimumFractionDigits: Number.isInteger(price) ? 0 : 2,
                maximumFractionDigits: 2,
            })}`;
        };
        const actionLink = (href, label, className = '') => {
            const link = document.createElement('a');
            link.href = href;
            link.textContent = label;
            if (className) link.className = className;
            return link;
        };
        const showUnit = (unit) => {
            const content = document.createElement('div');
            content.className = 'map-info-card';
            if (unit.image_url) {
                const image = document.createElement('img');
                image.src = unit.image_url;
                image.alt = `${unit.name} listing photo`;
                content.append(image);
            }
            const host = document.createElement('div');
            host.className = 'map-info-host';
            if (unit.host_avatar_url) {
                const avatar = document.createElement('img');
                avatar.src = unit.host_avatar_url;
                avatar.alt = unit.business_name || unit.host_name || 'Host';
                host.append(avatar);
            }
            const hostCopy = document.createElement('span');
            const hostLabel = document.createElement('small');
            hostLabel.textContent = 'Hosted by';
            const hostName = document.createElement('b');
            hostName.textContent = unit.business_name || unit.host_name || 'Verified host';
            hostCopy.append(hostLabel, hostName);
            host.append(hostCopy);
            content.append(host);
            const name = document.createElement('strong');
            name.textContent = unit.name;
            const location = document.createElement('span');
            location.textContent = unit.location || 'Location pinned by host';
            content.append(name, location);
            const facts = document.createElement('small');
            const factParts = [];
            if (unit.bedrooms) factParts.push(`${unit.bedrooms} BR`);
            if (unit.capacity) factParts.push(`Up to ${unit.capacity}`);
            const lowestPrice = priceFor(unit);
            if (lowestPrice) factParts.push(`Lowest price ${lowestPrice}`);
            facts.textContent = factParts.join(' · ');
            if (factParts.length) content.append(facts);
            if (unit.distance_km !== null && unit.distance_km !== undefined) {
                const distance = document.createElement('small');
                distance.textContent = `${Number(unit.distance_km).toFixed(1)} km from your search center`;
                content.append(distance);
            }
            const actions = document.createElement('div');
            actions.className = 'map-info-actions';
            if (unit.url) actions.append(actionLink(unit.url, 'View listing'));
            if (unit.inquiry_url) actions.append(actionLink(unit.inquiry_url, 'Inquire now'));
            if (unit.navigation_url) {
                const navigationLink = actionLink(unit.navigation_url, 'Navigate ↗', 'map-info-navigate');
                navigationLink.target = '_blank';
                navigationLink.rel = 'noopener';
                actions.append(navigationLink);
            }
            if (unit.host_url) actions.append(actionLink(unit.host_url, 'Host profile', 'map-info-host-link'));
            if (actions.childElementCount) content.append(actions);
            info.setContent(content);
            info.setPosition(unit.position);
            info.open({map});
        };

        const profileMarker = (unit) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'map-profile-marker';
            const lowestPrice = priceFor(unit);
            button.title = `${unit.name} — ${unit.business_name || unit.host_name || 'Host'}${lowestPrice ? ` — lowest price ${lowestPrice}` : ''}`;
            button.setAttribute('aria-label', `View ${unit.name} hosted by ${unit.business_name || unit.host_name || 'host'}${lowestPrice ? `, lowest price ${lowestPrice}` : ''}`);
            const avatar = document.createElement('span');
            avatar.className = 'map-profile-marker-avatar';
            const initials = document.createElement('span');
            initials.className = 'map-profile-marker-initials';
            initials.textContent = initialsFor(unit);
            avatar.append(initials);
            if (unit.marker_image_url) {
                const image = document.createElement('img');
                image.src = unit.marker_image_url;
                image.alt = '';
                image.addEventListener('error', () => image.remove());
                avatar.append(image);
            }
            button.append(avatar);
            if (lowestPrice) {
                const price = document.createElement('strong');
                price.className = 'map-profile-marker-price';
                price.textContent = lowestPrice;
                button.append(price);
            }
            button.addEventListener('click', () => showUnit(unit));
            return button;
        };
        const clusterMarker = (cluster) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'map-cluster-marker';
            button.textContent = String(cluster.units.length);
            button.title = `Zoom in to see ${cluster.units.length} listings`;
            button.setAttribute('aria-label', `Zoom in to see ${cluster.units.length} listings`);
            button.addEventListener('click', () => {
                const clusterBounds = new google.maps.LatLngBounds();
                cluster.units.forEach((unit) => clusterBounds.extend(unit.position));
                const currentZoom = map.getZoom() || 6;
                if (clusterBounds.getNorthEast().equals(clusterBounds.getSouthWest())) {
                    map.setCenter(cluster.position);
                    map.setZoom(Math.min(18, currentZoom + 2));
                } else {
                    map.fitBounds(clusterBounds, 70);
                    google.maps.event.addListenerOnce(map, 'idle', () => {
                        if ((map.getZoom() || 0) <= currentZoom) map.setZoom(Math.min(18, currentZoom + 2));
                    });
                }
            });
            return button;
        };

        const renderedOverlays = [];
        const manager = new google.maps.OverlayView();
        const clearRendered = () => {
            renderedOverlays.splice(0).forEach((overlay) => overlay.setMap(null));
        };
        const render = () => {
            const projection = manager.getProjection();
            if (!projection) return;
            clearRendered();
            const shouldCluster = (map.getZoom() || 0) < 12;
            const clusters = [];
            mappedUnits.forEach((unit) => {
                const pixel = projection.fromLatLngToDivPixel(unit.position);
                const cluster = shouldCluster ? clusters.find((item) => Math.hypot(item.x - pixel.x, item.y - pixel.y) < 64) : null;
                if (cluster) {
                    cluster.units.push(unit);
                    cluster.x = cluster.units.reduce((sum, item) => sum + projection.fromLatLngToDivPixel(item.position).x, 0) / cluster.units.length;
                    cluster.y = cluster.units.reduce((sum, item) => sum + projection.fromLatLngToDivPixel(item.position).y, 0) / cluster.units.length;
                    cluster.position = {lat: cluster.units.reduce((sum, item) => sum + item.position.lat, 0) / cluster.units.length, lng: cluster.units.reduce((sum, item) => sum + item.position.lng, 0) / cluster.units.length};
                } else {
                    clusters.push({units: [unit], x: pixel.x, y: pixel.y, position: unit.position});
                }
            });
            const duplicateTotals = new Map();
            const duplicateIndexes = new Map();
            if (!shouldCluster) mappedUnits.forEach((unit) => {
                const key = `${unit.position.lat.toFixed(6)},${unit.position.lng.toFixed(6)}`;
                duplicateTotals.set(key, (duplicateTotals.get(key) || 0) + 1);
            });
            clusters.forEach((cluster) => {
                let element;
                let offset = {x: 0, y: 0};
                if (cluster.units.length > 1) {
                    element = clusterMarker(cluster);
                } else {
                    const unit = cluster.units[0];
                    element = profileMarker(unit);
                    const key = `${unit.position.lat.toFixed(6)},${unit.position.lng.toFixed(6)}`;
                    const total = duplicateTotals.get(key) || 1;
                    const index = duplicateIndexes.get(key) || 0;
                    duplicateIndexes.set(key, index + 1);
                    if (total > 1) {
                        const angle = (Math.PI * 2 * index / total) - Math.PI / 2;
                        offset = {x: Math.cos(angle) * 34, y: Math.sin(angle) * 34};
                    }
                }
                const overlay = new MapBadgeOverlay(cluster.position, element, offset);
                overlay.setMap(map);
                renderedOverlays.push(overlay);
            });
        };
        manager.onAdd = render;
        manager.draw = () => {};
        manager.onRemove = clearRendered;
        manager.setMap(map);
        map.addListener('idle', render);

        return bounds;
    };

    const initializeListingMap = (container) => {
        const canvas = container.querySelector('[data-map-canvas]');
        const latitudeInput = container.querySelector('[data-map-latitude]');
        const longitudeInput = container.querySelector('[data-map-longitude]');
        const addressInput = container.querySelector('[data-map-address]');
        const coordinateLabel = container.querySelector('[data-map-coordinate-label]');
        const savedLatitude = numberValue(latitudeInput);
        const savedLongitude = numberValue(longitudeInput);
        const hasSavedPoint = savedLatitude !== null && savedLongitude !== null;
        const center = hasSavedPoint ? {lat: savedLatitude, lng: savedLongitude} : defaultCenter;
        const map = new google.maps.Map(canvas, mapOptions(container, center, hasSavedPoint ? 16 : 6));
        const geocoder = new google.maps.Geocoder();
        const marker = new google.maps.Marker({map, position: center, draggable: true, visible: hasSavedPoint, title: 'Listing location'});

        const setPoint = (position, updateAddress = false) => {
            latitudeInput.value = position.lat.toFixed(7);
            longitudeInput.value = position.lng.toFixed(7);
            latitudeInput.dispatchEvent(new Event('input', {bubbles: true}));
            longitudeInput.dispatchEvent(new Event('input', {bubbles: true}));
            marker.setPosition(position);
            marker.setVisible(true);
            map.panTo(position);
            map.setZoom(Math.max(map.getZoom(), 15));
            if (coordinateLabel) coordinateLabel.textContent = coordinateText(position);
            setStatus(container, 'Listing pin updated. Save the listing to keep it.');
            if (updateAddress) reverseGeocode(geocoder, position, addressInput);
        };

        map.addListener('click', (event) => setPoint({lat: event.latLng.lat(), lng: event.latLng.lng()}, true));
        marker.addListener('dragend', (event) => setPoint({lat: event.latLng.lat(), lng: event.latLng.lng()}, true));
        enableAddressSearch(container, map, geocoder, addressInput, (position) => setPoint(position));
        container.querySelector('[data-map-use-location]')?.addEventListener('click', () => geolocate(container, (position) => setPoint(position, true)));
        container.querySelector('[data-map-find-address]')?.addEventListener('click', () => geocodeAddress(container, geocoder, addressInput, (position) => setPoint(position)));
        // Do not write location data just by opening the listing form. The host
        // can explicitly search, click the map, or choose "Use my location".
    };

    const initializeSearchMap = (container) => {
        const form = container.closest('form');
        const canvas = container.querySelector('[data-map-canvas]');
        const latitudeInput = form.querySelector('[data-map-latitude]');
        const longitudeInput = form.querySelector('[data-map-longitude]');
        const radiusInput = form.querySelector('[data-radius-input]');
        const radiusOutput = form.querySelector('[data-radius-output]');
        const addressInput = form.querySelector('[data-map-address]');
        const coordinateLabel = container.querySelector('[data-map-coordinate-label]');
        const savedLatitude = numberValue(latitudeInput);
        const savedLongitude = numberValue(longitudeInput);
        const hasSavedPoint = savedLatitude !== null && savedLongitude !== null;
        const center = hasSavedPoint ? {lat: savedLatitude, lng: savedLongitude} : defaultCenter;
        const map = new google.maps.Map(canvas, mapOptions(container, center, hasSavedPoint ? 12 : 6));
        const geocoder = new google.maps.Geocoder();
        const centerMarker = new google.maps.Marker({map, position: center, draggable: true, visible: hasSavedPoint, title: 'Search center'});
        const circle = new google.maps.Circle({map: hasSavedPoint ? map : null, center, radius: Number(radiusInput.value) * 1000, fillColor: '#3e7b70', fillOpacity: .14, strokeColor: '#245f50', strokeOpacity: .8, strokeWeight: 2});
        const units = readUnits(container);
        const unitBounds = addUnitMarkers(map, units);

        if (!hasSavedPoint && units.length > 0) map.fitBounds(unitBounds, 45);

        const setPoint = (position, updateAddress = false) => {
            latitudeInput.value = position.lat.toFixed(7);
            longitudeInput.value = position.lng.toFixed(7);
            latitudeInput.dispatchEvent(new Event('input', {bubbles: true}));
            longitudeInput.dispatchEvent(new Event('input', {bubbles: true}));
            centerMarker.setPosition(position);
            centerMarker.setVisible(true);
            circle.setMap(map);
            circle.setCenter(position);
            const radiusBounds = circle.getBounds();
            if (radiusBounds) map.fitBounds(radiusBounds, 45);
            else map.panTo(position);
            if (coordinateLabel) coordinateLabel.textContent = coordinateText(position);
            setStatus(container, `Searching within ${radiusInput.value} km of this point.`);
            if (updateAddress) reverseGeocode(geocoder, position, addressInput);
        };
        const syncRadius = () => {
            const radius = Number(radiusInput.value);
            radiusOutput.textContent = `${radius} km`;
            circle.setRadius(radius * 1000);
            const radiusBounds = circle.getBounds();
            if (centerMarker.getVisible() && radiusBounds) map.fitBounds(radiusBounds, 45);
            if (centerMarker.getVisible()) setStatus(container, `Searching within ${radius} km of this point.`);
        };

        radiusInput.addEventListener('input', syncRadius);
        map.addListener('click', (event) => setPoint({lat: event.latLng.lat(), lng: event.latLng.lng()}, true));
        centerMarker.addListener('dragend', (event) => setPoint({lat: event.latLng.lat(), lng: event.latLng.lng()}, true));
        enableAddressSearch(container, map, geocoder, addressInput, (position) => setPoint(position));
        container.querySelector('[data-map-use-location]')?.addEventListener('click', () => geolocate(container, (position) => setPoint(position, true)));
        container.querySelector('[data-map-find-address]')?.addEventListener('click', () => geocodeAddress(container, geocoder, addressInput, (position) => setPoint(position)));
        container.querySelector('[data-map-clear]')?.addEventListener('click', () => {
            latitudeInput.value = '';
            longitudeInput.value = '';
            centerMarker.setVisible(false);
            circle.setMap(null);
            if (coordinateLabel) coordinateLabel.textContent = 'No center selected';
            setStatus(container, 'Map radius cleared. The typed location will be used instead.');
        });
        syncRadius();
        if (!hasSavedPoint) geolocate(container, (position) => setPoint(position, true));
    };

    const initializeOverviewMap = (container) => {
        const canvas = container.querySelector('[data-map-canvas]');
        const units = readUnits(container);
        const map = new google.maps.Map(canvas, mapOptions(container, defaultCenter, 6));
        const bounds = addUnitMarkers(map, units);
        const defaultRadiusKm = Number(container.dataset.defaultRadiusKm || 500);
        const nearbyCircle = new google.maps.Circle({map: null, center: defaultCenter, radius: defaultRadiusKm * 1000, fillColor: '#3e7b70', fillOpacity: .1, strokeColor: '#245f50', strokeOpacity: .65, strokeWeight: 2});
        if (units.length > 0) map.fitBounds(bounds, 55);
        else setStatus(container, 'No hosts have pinned a public listing location yet.');

        let userMarker = null;
        const centerOnCurrentLocation = () => geolocate(container, (position) => {
            nearbyCircle.setCenter(position);
            nearbyCircle.setMap(map);
            const radiusBounds = nearbyCircle.getBounds();
            if (radiusBounds) map.fitBounds(radiusBounds, 45);
            else map.panTo(position);
            if (userMarker) userMarker.setPosition(position);
            else userMarker = new google.maps.Marker({map, position, title: 'Your location', icon: {path: google.maps.SymbolPath.CIRCLE, scale: 7, fillColor: '#173c34', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 3}});
            setStatus(container, `Map centered on your location with a ${defaultRadiusKm} km nearby area.`);
        });

        container.querySelector('[data-map-use-location]')?.addEventListener('click', centerOnCurrentLocation);
        const mapPanel = container.closest('[data-listing-map-panel]');
        container.addEventListener('davao:listing-map-visible', () => {
            google.maps.event.trigger(map, 'resize');
            if (units.length > 0) map.fitBounds(bounds, 55);
        });
        if (!mapPanel?.hidden) centerOnCurrentLocation();
    };

    const initializeMaps = () => {
        if (!mapsAvailable()) return;
        document.querySelectorAll('[data-listing-location-map], [data-search-location-map], [data-overview-nearby-map]').forEach((container) => {
            if (container.dataset.mapInitialized === '1') return;
            container.dataset.mapInitialized = '1';
            if (container.matches('[data-listing-location-map]')) initializeListingMap(container);
            else if (container.matches('[data-search-location-map]')) initializeSearchMap(container);
            else initializeOverviewMap(container);
            watchForMapsAuthFailure(container);
        });
    };

    window.addEventListener('mybooking:maps-ready', initializeMaps);
    window.addEventListener('mybooking:maps-auth-failure', showMapsAuthFailure);
    document.addEventListener('DOMContentLoaded', () => {
        if (window.myBookingMapsAuthFailed) showMapsAuthFailure();
        document.querySelectorAll('[data-radius-input]').forEach((input) => {
            const output = input.closest('form')?.querySelector('[data-radius-output]');
            input.addEventListener('input', () => {
                if (output) output.textContent = `${input.value} km`;
            });
        });
        initializeMaps();
    });
})();
