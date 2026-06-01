/**
 * Map Module
 * Handles Leaflet map initialization and markers
 */

let alumniMap = null;
let markersLayer = null;

/**
 * Initialize the Leaflet map
 */
function initMap() {
    if (alumniMap) {
        alumniMap.invalidateSize();
        return;
    }

    alumniMap = L.map('leaflet-map').setView([39.0, 22.5], 5); // Centered on Greece

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
        maxZoom: 18,
    }).addTo(alumniMap);

    markersLayer = L.layerGroup().addTo(alumniMap);
}

/**
 * Place markers for all alumni on the map
 */
function placeAlumniMarkers(alumni, onMarkerClick) {
    if (!markersLayer) return;
    
    markersLayer.clearLayers();
    const bounds = [];

    alumni.forEach(alumnus => {
        // Use the first job's coordinates (preferably the current job)
        const jobs = alumnus.jobs || [];
        const job = jobs.find(j => j.is_current) || jobs[0];
        
        if (!job) return;
        
        const lat = parseFloat(job.latitude);
        const lng = parseFloat(job.longitude);
        
        if (isNaN(lat) || isNaN(lng)) return;

        bounds.push([lat, lng]);

        const marker = L.marker([lat, lng])
            .bindPopup(createPopupContent(alumnus, job))
            .on('click', () => {
                if (onMarkerClick) onMarkerClick(alumnus);
            });

        markersLayer.addLayer(marker);
    });

    // Fit map to bounds if we have markers
    if (bounds.length > 1) {
        alumniMap.fitBounds(bounds, { padding: [30, 30] });
    } else if (bounds.length === 1) {
        alumniMap.setView(bounds[0], 10);
    }
}

/**
 * Create HTML content for the popup
 */
function createPopupContent(alumnus, job) {
    const name = `${alumnus.first_name} ${alumnus.last_name}`;
    let html = `<h6>${name}</h6>`;
    
    if (job) {
        html += `<div class="popup-job">
            <strong>${job.job_title}</strong> @ ${job.company_name}<br>
            ${job.city}, ${job.country}
        </div>`;
    } else {
        html += `<div class="popup-job text-muted">No current job listed</div>`;
    }
    
    html += `<div class="mt-1">
        <small>(${alumnus.enrollment_year} - ${alumnus.graduation_year})</small>
    </div>`;
    
    return html;
}

/**
 * Invalidate map size (useful after tab change or resize)
 */
function invalidateMap() {
    if (alumniMap) {
        setTimeout(() => alumniMap.invalidateSize(), 200);
    }
}