// Alpine-компонент поля LocationPicker. Звичайний ES-модуль без збірки: Filament завантажує його через x-load.
// MapLibre GL JS (UMD, глобальний maplibregl) підвантажується один раз на сторінку.

let maplibreLoading = null

function loadMaplibre(src) {
    if (window.maplibregl) return Promise.resolve()

    maplibreLoading ??= new Promise((resolve, reject) => {
        const script = document.createElement('script')
        script.src = src
        script.onload = resolve
        script.onerror = reject
        document.head.appendChild(script)
    })

    return maplibreLoading
}

// ~1 см точності, як і дає Nominatim
const round = (value) => Math.round(value * 1e7) / 1e7

export default function locationPicker({ key, lat, lng, source, maplibreSrc, map, labels }) {
    return {
        lat,
        lng,
        source,
        busy: false,
        message: null,
        candidates: [],
        replaceManualForChoice: false,
        mapInstance: null,
        marker: null,

        async init() {
            await loadMaplibre(maplibreSrc)

            const hasPoint = this.hasPoint()
            this.mapInstance = new window.maplibregl.Map({
                container: this.$refs.map,
                style: map.styleUrl,
                center: hasPoint ? [this.lng, this.lat] : map.center,
                zoom: hasPoint ? map.pointZoom : map.zoom,
                attributionControl: false,
            })
            this.mapInstance.addControl(
                new window.maplibregl.AttributionControl({ compact: false, customAttribution: map.attribution }),
            )
            this.mapInstance.addControl(new window.maplibregl.NavigationControl({ showCompass: false }), 'top-right')

            // Клік по карті ставить пін вручну (коли адресу не знайдено або пін ще не поставлено)
            this.mapInstance.on('click', (event) => this.setManual(event.lngLat))

            this.syncMarker()
            // Координати могли змінитися на сервері (пошук) — пересуваємо пін
            this.$watch('lat', () => this.syncMarker())
            this.$watch('lng', () => this.syncMarker())
        },

        hasPoint() {
            return this.lat !== null && this.lat !== '' && this.lng !== null && this.lng !== ''
        },

        syncMarker() {
            if (!this.mapInstance) return

            if (!this.hasPoint()) {
                this.marker?.remove()
                this.marker = null
                return
            }

            const position = [Number(this.lng), Number(this.lat)]

            if (!this.marker) {
                this.marker = new window.maplibregl.Marker({ draggable: true }).setLngLat(position).addTo(this.mapInstance)
                this.marker.on('dragend', () => this.setManual(this.marker.getLngLat()))
            } else {
                this.marker.setLngLat(position)
            }
        },

        setManual(lngLat) {
            this.lat = round(lngLat.lat)
            this.lng = round(lngLat.lng)
            this.source = 'manual'
            this.candidates = []
            this.message = null
        },

        async search(replaceManual = false) {
            const response = await this.call('search', { replaceManual })
            if (!response) return

            if (response.status === 'confirm_replace') {
                if (window.confirm(labels.confirmReplace)) await this.search(true)
                return
            }

            if (response.status === 'choose') {
                this.candidates = response.candidates
                this.replaceManualForChoice = replaceManual
                return
            }

            this.handle(response)
        },

        async choose(index) {
            const response = await this.call('choose', { index, replaceManual: this.replaceManualForChoice })
            if (response) this.handle(response)
        },

        handle(response) {
            if (response.status === 'found') {
                this.candidates = []
                // Сервер уже записав стан; тут — щоб пін рухнувся одразу, не чекаючи синхронізації
                this.lat = response.lat
                this.lng = response.lng
                this.source = 'geocoder'
                this.mapInstance?.flyTo({ center: [response.lng, response.lat], zoom: map.pointZoom })
                return
            }

            this.message = response.message ?? null
        },

        async call(method, args) {
            this.busy = true
            this.message = null

            try {
                return await this.$wire.callSchemaComponentMethod(key, method, args)
            } finally {
                this.busy = false
            }
        },
    }
}
