{{-- Leaflet map picker JS — include trong @section('script'), sau _map_picker.blade.php --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const latInput = document.querySelector('input[name="lat"]');
    const lngInput = document.querySelector('input[name="lng"]');
    const mapDiv  = document.getElementById('station-map');
    const btnGeo  = document.getElementById('btn-geocode-address');
    if (!mapDiv || !latInput || !lngInput) return;

    // Đà Lạt làm trung tâm mặc định khi chưa có tọa độ
    const DEFAULT_CENTER = [11.9465, 108.4419];
    const initLat = parseFloat(latInput.value);
    const initLng = parseFloat(lngInput.value);
    const hasCoords = !isNaN(initLat) && !isNaN(initLng);

    const map = L.map(mapDiv).setView(
        hasCoords ? [initLat, initLng] : DEFAULT_CENTER,
        hasCoords ? 15 : 13
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    let marker = null;

    function setMarker(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', function (e) {
                const p = e.target.getLatLng();
                latInput.value = p.lat.toFixed(7);
                lngInput.value = p.lng.toFixed(7);
            });
        }
    }

    if (hasCoords) setMarker(initLat, initLng);

    // Click bản đồ → đặt/di chuyển marker, điền inputs
    map.on('click', function (e) {
        setMarker(e.latlng.lat, e.latlng.lng);
        latInput.value = e.latlng.lat.toFixed(7);
        lngInput.value = e.latlng.lng.toFixed(7);
    });

    // Khi admin sửa thủ công ô input → marker di chuyển theo
    [latInput, lngInput].forEach(function (inp) {
        inp.addEventListener('change', function () {
            const la = parseFloat(latInput.value);
            const ln = parseFloat(lngInput.value);
            if (!isNaN(la) && !isNaN(ln)) {
                setMarker(la, ln);
                map.setView([la, ln], map.getZoom());
            }
        });
    });

    // Nút "Tìm từ địa chỉ" — Nominatim geocoding (miễn phí, không cần API key)
    if (btnGeo) {
        btnGeo.addEventListener('click', async function () {
            const addrEl = document.querySelector('textarea[name="address"]');
            const addr = addrEl ? addrEl.value.trim() : '';
            if (!addr) {
                alert('Vui lòng nhập địa chỉ trước.');
                return;
            }
            btnGeo.disabled = true;
            btnGeo.textContent = 'Đang tìm…';
            try {
                const res = await fetch(
                    'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(addr),
                    { headers: { 'Accept-Language': 'vi', 'User-Agent': 'VietponicsAdmin/1.0' } }
                );
                const data = await res.json();
                if (!data.length) {
                    alert('Không tìm thấy địa chỉ. Thử nhập đầy đủ hơn (ví dụ: thêm tên tỉnh/thành phố).');
                    return;
                }
                const la = parseFloat(data[0].lat);
                const ln = parseFloat(data[0].lon);
                latInput.value = la.toFixed(7);
                lngInput.value = ln.toFixed(7);
                setMarker(la, ln);
                map.setView([la, ln], 16);
            } catch (e) {
                alert('Lỗi kết nối. Vui lòng thử lại sau.');
            } finally {
                btnGeo.disabled = false;
                btnGeo.textContent = 'Tìm từ địa chỉ';
            }
        });
    }
})();
</script>
