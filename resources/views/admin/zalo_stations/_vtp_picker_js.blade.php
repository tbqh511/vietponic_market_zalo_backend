{{-- JS cho cascade province → district + ward. Include trong @section('script'). --}}
<script>
(function () {
    const provSel = document.getElementById('vtp_province_id');
    const distSel = document.getElementById('vtp_district_id');
    const wardSel = document.getElementById('vtp_ward_id');
    if (!provSel || !distSel || !wardSel) return;

    const API = '{{ url('/api/locations') }}';

    async function loadProvinces() {
        try {
            const res = await fetch(`${API}/provinces`);
            const json = await res.json();
            const cur = provSel.dataset.current;
            for (const p of (json.data || [])) {
                const o = new Option(p.name, p.id);
                if (String(p.id) === cur) o.selected = true;
                provSel.add(o);
            }
            if (cur) await loadChildren(cur);
        } catch (e) {
            console.error('Lỗi tải danh sách tỉnh:', e);
        }
    }

    async function loadChildren(provinceId) {
        // Reset children
        distSel.length = 1;
        wardSel.length = 1;
        if (!provinceId) return;
        try {
            const [resD, resW] = await Promise.all([
                fetch(`${API}/districts?province_id=${provinceId}`).then(r => r.json()),
                fetch(`${API}/wards?province_id=${provinceId}`).then(r => r.json()),
            ]);
            const preD = distSel.dataset.current;
            const preW = wardSel.dataset.current;
            for (const d of (resD.data || [])) {
                const o = new Option(d.name, d.id);
                if (String(d.id) === preD) o.selected = true;
                distSel.add(o);
            }
            for (const w of (resW.data || [])) {
                const o = new Option(w.name, w.id);
                if (String(w.id) === preW) o.selected = true;
                wardSel.add(o);
            }
        } catch (e) {
            console.error('Lỗi tải quận/huyện/phường:', e);
        }
    }

    provSel.addEventListener('change', (e) => {
        // Khi đổi tỉnh, xoá preselect cũ để không "nhảy" về giá trị cũ
        distSel.dataset.current = '';
        wardSel.dataset.current = '';
        loadChildren(e.target.value);
    });

    loadProvinces();
})();
</script>
