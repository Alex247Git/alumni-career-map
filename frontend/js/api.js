/**
 * API Helper Module
 * All AJAX calls to the Alumni REST API
 */

const API_BASE = 'http://alumni.ds.uth.gr';

/**
 * Generic API request
 */
async function apiRequest(method, endpoint, data = null, token = null) {
    const url = API_BASE + endpoint;
    const options = {
        method: method,
        headers: {}
    };

    if (data) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
    }

    if (token) {
        options.headers['Authorization'] = 'Bearer ' + token;
    }

    try {
        const response = await fetch(url, options);
        const contentType = response.headers.get('content-type') || '';
        
        let responseData;
        if (contentType.includes('application/json')) {
            responseData = await response.json();
        } else if (contentType.includes('application/xml') || contentType.includes('text/xml')) {
            const text = await response.text();
            responseData = { xml: text };  // Return raw XML string
        } else {
            // For plain integer responses (e.g., /alumni/count)
            const text = await response.text();
            responseData = { raw: text };
        }

        return {
            ok: response.ok,
            status: response.status,
            data: responseData
        };
    } catch (error) {
        console.error('API Request failed:', error);
        return {
            ok: false,
            status: 0,
            data: { status: 'error', message: 'Network error: ' + error.message }
        };
    }
}

// ============== AUTH ENDPOINTS ==============

/**
 * POST /api/v1/auth/login
 * Endpoint #2: Login and get JWT token
 */
async function apiLogin(email, password) {
    return apiRequest('POST', '/api/v1/auth/login', { email, password });
}

/**
 * POST /api/v1/alumni/
 * Endpoint #1: Register new alumnus
 */
async function apiRegister(data, token) {
    return apiRequest('POST', '/api/v1/alumni', data, token);
}

// ============== ALUMNI ENDPOINTS ==============

/**
 * GET /api/v1/alumni/count
 * Endpoint #3: Get total alumni count
 */
async function apiGetAlumniCount(token) {
    return apiRequest('GET', '/api/v1/alumni/count', null, token);
}

/**
 * GET /api/v1/alumni/
 * Endpoint #5: Get all alumni
 */
async function apiGetAllAlumni(token) {
    return apiRequest('GET', '/api/v1/alumni', null, token);
}

/**
 * GET /api/v1/alumni/search
 * Endpoint #8: Search alumni with criteria + pagination + format
 */
async function apiSearchAlumni(params, token) {
    const query = new URLSearchParams();
    if (params.name) query.set('name', params.name);
    if (params.enrollment_year) query.set('enrollment_year', params.enrollment_year);
    if (params.graduation_year) query.set('graduation_year', params.graduation_year);
    if (params.country) query.set('country', params.country);
    query.set('page', params.page || '1');
    query.set('format', params.format || 'json');
    
    return apiRequest('GET', '/api/v1/alumni/search?' + query.toString(), null, token);
}

/**
 * GET /api/v1/alumni/{id}/jobs
 * Endpoint #4: Get jobs of a specific alumnus
 */
async function apiGetAlumnusJobs(alumnusId, token) {
    return apiRequest('GET', `/api/v1/alumni/${alumnusId}/jobs`, null, token);
}

// ============== JOBS ENDPOINTS ==============

/**
 * PUT /api/v1/alumni/{id}/jobs/{jobId}
 * Endpoint #7: Update a job
 */
async function apiUpdateJob(alumnusId, jobId, data, token) {
    return apiRequest('PUT', `/api/v1/alumni/${alumnusId}/jobs/${jobId}`, data, token);
}

/**
 * DELETE /api/v1/alumni/{id}/jobs/{jobId}
 * Endpoint #6: Delete a job
 */
async function apiDeleteJob(alumnusId, jobId, token) {
    return apiRequest('DELETE', `/api/v1/alumni/${alumnusId}/jobs/${jobId}`, null, token);
}