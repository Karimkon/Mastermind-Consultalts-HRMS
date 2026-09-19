{{--
    Map location picker.

    Search for the premises by name or address, or click / drag the pin, and the
    latitude and longitude inputs fill themselves. Replaces having to stand at
    the site and read the device GPS — an admin can set a client's location from
    the office.

    Built on Leaflet with OpenStreetMap and Esri satellite imagery, and Nominatim
    for search. All three are free and need no API key or billing account, so
    there is nothing to sign up for and nothing to pay. The satellite layer is
    there because a compound is far easier to pin from the air than from a
    street map.

    Props:
      lat-input     id of the latitude  input to write to   (required)
      lng-input     id of the longitude input to write to   (required)
      address-input id of an address input to fill from the picked place
      radius-input  id of a radius input; draws the geo-fence circle live
      label         heading shown above the map
      height        map height, default 340px
--}}
@props([
    'latInput',
    'lngInput',
    'addressInput' => null,
    'radiusInput'  => null,
    'label'        => 'Pick the location on the map',
    'height'       => '340px',
    // Kampala city centre — only used when nothing is set yet.
    'defaultLat'   => 0.3476,
    'defaultLng'   => 32.5825,
])

@once
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
    .map-picker { border-radius: 10px; border: 1px solid #e2e8f0; z-index: 0; }
    .map-picker-search { position: relative; }
    .map-picker-results {
        position: absolute; top: 100%; left: 0; right: 0; z-index: 1000;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
        box-shadow: 0 8px 24px -12px rgba(15,23,42,.25); max-height: 240px; overflow-y: auto;
    }
    .map-picker-results button {
        display: block; width: 100%; text-align: left; padding: 8px 12px;
        font-size: 13px; color: #334155; border-bottom: 1px solid #f1f5f9;
    }
    .map-picker-results button:hover { background: #f8fafc; }
    .map-picker-results button:last-child { border-bottom: none; }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
/** Wire one map picker. Called once per instance from the markup below. */
window.initMapPicker = function (opts) {
    const latEl  = document.getElementById(opts.latInput);
    const lngEl  = document.getElementById(opts.lngInput);
    const addrEl = opts.addressInput ? document.getElementById(opts.addressInput) : null;
    const radEl  = opts.radiusInput  ? document.getElementById(opts.radiusInput)  : null;
    if (!latEl || !lngEl || !document.getElementById(opts.mapId)) return;

    const startLat = parseFloat(latEl.value) || opts.defaultLat;
    const startLng = parseFloat(lngEl.value) || opts.defaultLng;
    const isSet    = !!(parseFloat(latEl.value) && parseFloat(lngEl.value));

    const map = L.map(opts.mapId).setView([startLat, startLng], isSet ? 18 : 12);

    const streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    });

    // Aerial view — a fenced compound is much easier to pin from above.
    const satellite = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics',
    });

    // Place names on top of the imagery, so the satellite view stays readable.
    const labels = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, opacity: 0.9,
    });
    const hybrid = L.layerGroup([satellite, labels]);

    hybrid.addTo(map);
    L.control.layers({ 'Satellite': hybrid, 'Street map': streets }, null, { position: 'topright' }).addTo(map);

    const marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

    // The geo-fence circle turns an abstract radius into something you can see.
    let circle = null;
    function drawCircle() {
        if (!radEl) return;
        const r = parseFloat(radEl.value);
        if (circle) { map.removeLayer(circle); circle = null; }
        if (r > 0) {
            circle = L.circle(marker.getLatLng(), {
                radius: r, color: '#2563eb', fillColor: '#3b82f6', fillOpacity: 0.15, weight: 1,
            }).addTo(map);
        }
    }

    async function reverseGeocode(lat, lng) {
        // Only ever fills a blank address box — never overwrites typed text.
        if (!addrEl || addrEl.value.trim()) return;
        try {
            const url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data && data.display_name) addrEl.value = data.display_name;
        } catch (e) { /* an address is a convenience, not a requirement */ }
    }

    function setPoint(lat, lng, { pan = false, reverse = false } = {}) {
        marker.setLatLng([lat, lng]);
        latEl.value = Number(lat).toFixed(7);
        lngEl.value = Number(lng).toFixed(7);
        latEl.dispatchEvent(new Event('change', { bubbles: true }));
        lngEl.dispatchEvent(new Event('change', { bubbles: true }));
        drawCircle();
        if (pan) map.setView([lat, lng], Math.max(map.getZoom(), 18));
        if (reverse) reverseGeocode(lat, lng);
    }

    map.on('click', (e) => setPoint(e.latlng.lat, e.latlng.lng, { reverse: true }));
    marker.on('dragend', () => {
        const p = marker.getLatLng();
        setPoint(p.lat, p.lng, { reverse: true });
    });
    if (radEl) radEl.addEventListener('input', drawCircle);
    drawCircle();

    // Leaflet mis-measures itself when it starts inside a hidden tab; nudge it
    // once the browser has settled, and again whenever the tab becomes visible.
    setTimeout(() => map.invalidateSize(), 300);
    new ResizeObserver(() => map.invalidateSize())
        .observe(document.getElementById(opts.mapId));

    // ── Search (Nominatim) ────────────────────────────────────────────────
    const searchEl  = document.getElementById(opts.searchId);
    const resultsEl = document.getElementById(opts.resultsId);
    let timer = null;

    function hideResults() { resultsEl.innerHTML = ''; resultsEl.style.display = 'none'; }

    if (searchEl) {
        // Enter would otherwise submit the surrounding form mid-search.
        searchEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });

        searchEl.addEventListener('input', () => {
            clearTimeout(timer);
            const q = searchEl.value.trim();
            if (q.length < 3) { hideResults(); return; }

            // Debounced: Nominatim's usage policy allows about one call a second.
            timer = setTimeout(async () => {
                try {
                    const url = 'https://nominatim.openstreetmap.org/search'
                              + '?format=json&limit=6&countrycodes=ug&q=' + encodeURIComponent(q);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const places = await res.json();

                    resultsEl.innerHTML = '';
                    if (!places.length) {
                        resultsEl.innerHTML =
                            '<div style="padding:10px 12px;font-size:13px;color:#94a3b8">No place found. '
                          + 'Try a nearby landmark, or switch to Satellite and click straight on the map.</div>';
                        resultsEl.style.display = 'block';
                        return;
                    }

                    places.forEach((p) => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.textContent = p.display_name;
                        b.addEventListener('click', () => {
                            setPoint(parseFloat(p.lat), parseFloat(p.lon), { pan: true });
                            if (addrEl && !addrEl.value.trim()) addrEl.value = p.display_name;
                            searchEl.value = p.display_name;
                            hideResults();
                        });
                        resultsEl.appendChild(b);
                    });
                    resultsEl.style.display = 'block';
                } catch (e) {
                    resultsEl.innerHTML =
                        '<div style="padding:10px 12px;font-size:13px;color:#ef4444">Search is unavailable '
                      + 'right now. Click the map to set the point instead.</div>';
                    resultsEl.style.display = 'block';
                }
            }, 600);
        });

        document.addEventListener('click', (e) => {
            if (!resultsEl.contains(e.target) && e.target !== searchEl) hideResults();
        });
    }

    // ── Use my current location ───────────────────────────────────────────
    const gpsBtn = document.getElementById(opts.gpsId);
    if (gpsBtn) {
        gpsBtn.addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('This browser cannot report a location. Search or click the map instead.');
                return;
            }
            const original = gpsBtn.innerHTML;
            gpsBtn.disabled = true;
            gpsBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Locating…';
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    setPoint(pos.coords.latitude, pos.coords.longitude, { pan: true, reverse: true });
                    gpsBtn.disabled = false; gpsBtn.innerHTML = original;
                },
                () => {
                    alert('Could not get your location. Search for the place or click the map instead.');
                    gpsBtn.disabled = false; gpsBtn.innerHTML = original;
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });
    }
};
</script>
@endpush
@endonce

@php
    $uid = 'mp' . substr(md5($latInput . $lngInput), 0, 8);
@endphp

<div class="mt-4">
    <label class="form-label">{{ $label }}</label>

    <div class="map-picker-search mb-2">
        <div class="relative">
            <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" id="{{ $uid }}Search" autocomplete="off" class="form-input pl-9"
                   placeholder="Search the premises — e.g. Roofings Uganda Lubowa, or Katula Road Kisaasi">
        </div>
        <div id="{{ $uid }}Results" class="map-picker-results" style="display:none"></div>
    </div>

    <div id="{{ $uid }}Map" class="map-picker" style="height: {{ $height }}"></div>

    <div class="flex flex-wrap items-center gap-3 mt-2">
        <button type="button" id="{{ $uid }}Gps" class="btn-secondary text-sm">
            <i class="fas fa-crosshairs mr-1"></i> Use My Current Location
        </button>
        <p class="text-xs text-slate-500">
            Search for the place, or click the map to drop the pin. Drag the pin to fine-tune.
            Use the <strong>Satellite</strong> layer (top right) to spot the compound.
        </p>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        window.initMapPicker({
            mapId:        '{{ $uid }}Map',
            searchId:     '{{ $uid }}Search',
            resultsId:    '{{ $uid }}Results',
            gpsId:        '{{ $uid }}Gps',
            latInput:     '{{ $latInput }}',
            lngInput:     '{{ $lngInput }}',
            addressInput: @json($addressInput),
            radiusInput:  @json($radiusInput),
            defaultLat:   {{ $defaultLat }},
            defaultLng:   {{ $defaultLng }},
        });
    });
</script>
@endpush
