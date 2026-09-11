/**
 * Main Application Module
 * Initializes the SPA, manages modals, login, search, CRUD operations
 */

// ============== STATE ==============
let state = {
    token: localStorage.getItem('alumni_token') || null,
    currentUser: JSON.parse(localStorage.getItem('alumni_user') || 'null'),
    allAlumni: [],
    currentPage: 1,
    clientPage: 1,
    isSearchActive: false,
    searchParams: {},
    searchResults: null,
    selectedAlumnus: null,
};

// ============== INITIALIZATION ==============
document.addEventListener('DOMContentLoaded', async () => {
    initMap();
    updateAuthUI();

    // Wait for Google Charts to load
    google.charts.setOnLoadCallback(async () => {
        await loadAllData();
    });

    // Bootstrap modals initialization
    const loginModalEl = document.getElementById('loginModal');
    const jobsModalEl = document.getElementById('jobsModal');
    const registerModalEl = document.getElementById('registerModal');
    const jobFormModalEl = document.getElementById('jobFormModal');

    if (loginModalEl) new bootstrap.Modal(loginModalEl);
    if (jobsModalEl) new bootstrap.Modal(jobsModalEl);
    if (registerModalEl) new bootstrap.Modal(registerModalEl);
    if (jobFormModalEl) new bootstrap.Modal(jobFormModalEl);

    // Login form submit
    document.getElementById('loginForm')?.addEventListener('submit', handleLogin);
    document.getElementById('registerForm')?.addEventListener('submit', handleRegister);
    document.getElementById('searchForm')?.addEventListener('submit', handleSearch);
    document.getElementById('jobForm')?.addEventListener('submit', handleJobFormSubmit);
});

// ============== DATA LOADING ==============
async function loadAllData() {
    try {
        let result = await apiGetAllAlumni(state.token);

        if (result.ok) {
            state.allAlumni = result.data.data || [];
        } else if (state.token) {
            // Token might be expired, try without
            state.token = null;
            localStorage.removeItem('alumni_token');
            localStorage.removeItem('alumni_user');
            updateAuthUI();
            result = await apiGetAllAlumni(null);
            state.allAlumni = result.ok ? (result.data.data || []) : [];
        }

        // Render: show first page of 4, with client-side pagination
        state.isSearchActive = false;
        state.clientPage = 1;
        placeAlumniMarkers(state.allAlumni, (alumnus) => openJobsModal(alumnus));
        renderClientAlumniPage(1);
        drawCountryChart(state.allAlumni);
        updateStats();
    } catch (e) {
        console.error('Failed to load data:', e);
        showToast('Error loading data. Make sure the API server is running.', 'danger');
    }
}

// ============== AUTH UI ==============
function updateAuthUI() {
    const loginBtn = document.getElementById('loginBtn');
    const registerBtn = document.getElementById('registerBtn');
    const userInfo = document.getElementById('userInfo');

    if (state.token && state.currentUser) {
        loginBtn.innerHTML = '<i class="bi bi-box-arrow-right"></i> Logout';
        loginBtn.onclick = handleLogout;
        loginBtn.className = 'btn btn-outline-light btn-sm';
        if (registerBtn) registerBtn.style.display = 'none';
        userInfo.innerHTML = `<span class="text-light ms-2"><i class="bi bi-person-circle"></i> ${state.currentUser.first_name}</span>`;
    } else {
        loginBtn.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Login';
        loginBtn.onclick = () => showModal('loginModal');
        loginBtn.className = 'btn btn-outline-light btn-sm';
        if (registerBtn) registerBtn.style.display = '';
        userInfo.innerHTML = '';
    }
}

function handleLogout() {
    state.token = null;
    state.currentUser = null;
    localStorage.removeItem('alumni_token');
    localStorage.removeItem('alumni_user');
    updateAuthUI();
    showToast('Logged out successfully', 'success');
}

// ============== AUTH MODAL FUNCTIONS ==============
function showModal(id) {
    const el = document.getElementById(id);
    if (el) {
        const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
        modal.show();
    }
}

function hideModal(id) {
    const el = document.getElementById(id);
    if (el) {
        const modal = bootstrap.Modal.getInstance(el);
        if (modal) modal.hide();
    }
}

function showLoginModal() {
    document.getElementById('loginTab').click();
    showModal('loginModal');
}

function showRegisterForm() {
    hideModal('loginModal');
    showModal('registerModal');
}

// ============== LOGIN ==============
async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    const btn = document.querySelector('#loginForm button[type="submit"]');
    const spinner = document.getElementById('loginSpinner');

    btn.disabled = true;
    spinner.style.display = 'inline-block';

    const result = await apiLogin(email, password);

    btn.disabled = false;
    spinner.style.display = 'none';

    if (result.ok && result.data.status === 'success') {
        state.token = result.data.token;
        state.currentUser = result.data.data;
        localStorage.setItem('alumni_token', state.token);
        localStorage.setItem('alumni_user', JSON.stringify(state.currentUser));
        updateAuthUI();
        hideModal('loginModal');
        document.getElementById('loginForm').reset();
        showToast('Login successful!', 'success');
        await loadAllData();
    } else {
        showToast(result.data.message || 'Login failed', 'danger');
    }
}

// ============== REGISTER ==============
async function handleRegister(e) {
    e.preventDefault();
    const data = {
        first_name: document.getElementById('regFirstName').value,
        last_name: document.getElementById('regLastName').value,
        email: document.getElementById('regEmail').value,
        password: document.getElementById('regPassword').value,
        enrollment_year: parseInt(document.getElementById('regEnrollmentYear').value),
        graduation_year: parseInt(document.getElementById('regGraduationYear').value),
    };
    const btn = document.querySelector('#registerForm button[type="submit"]');
    const spinner = document.getElementById('registerSpinner');

    btn.disabled = true;
    spinner.style.display = 'inline-block';

    const result = await apiRegister(data, state.token);

    btn.disabled = false;
    spinner.style.display = 'none';

    if (result.ok) {
        hideModal('registerModal');
        document.getElementById('registerForm').reset();
        showToast('Alumnus registered successfully!', 'success');
        
        // Auto-login after registration
        const loginResult = await apiLogin(data.email, data.password);
        if (loginResult.ok && loginResult.data.status === 'success') {
            state.token = loginResult.data.token;
            state.currentUser = loginResult.data.data;
            localStorage.setItem('alumni_token', state.token);
            localStorage.setItem('alumni_user', JSON.stringify(state.currentUser));
            updateAuthUI();
        }
        
        await loadAllData();
        
        // If auto-login succeeded, open jobs modal for the new user to add a job
        if (state.currentUser) {
            setTimeout(() => {
                openJobsModal(state.currentUser);
            }, 500);
        }
    } else {
        showToast(result.data.message || 'Registration failed', 'danger');
    }
}

// ============== ALUMNI LIST ==============
function renderAlumniList(alumniList) {
    const container = document.getElementById('alumniList');
    if (!container) return;

    const list = alumniList || state.allAlumni;

    if (!list || list.length === 0) {
        container.innerHTML = '<div class="text-center text-muted p-4"><i class="bi bi-search"></i> No results found</div>';
        document.getElementById('pagination').innerHTML = '';
        return;
    }

    container.innerHTML = list.map(a => `
        <div class="alumnus-item" onclick="openJobsModal({id:${a.id}, first_name:'${a.first_name.replace(/'/g, "\\'")}', last_name:'${a.last_name.replace(/'/g, "\\'")}'})">
            <div class="name">${a.first_name} ${a.last_name}</div>
            <div class="email">${a.email}</div>
            <div>
                <span class="badge bg-primary year-badge">${a.enrollment_year}</span>
                <span class="badge bg-secondary year-badge ms-1">${a.graduation_year}</span>
            </div>
        </div>
    `).join('');
}

// ============== CLIENT-SIDE PAGINATION ==============
const PER_PAGE = 4;

function renderClientAlumniPage(page) {
    state.clientPage = page;
    const totalPages = Math.ceil(state.allAlumni.length / PER_PAGE);
    const start = (page - 1) * PER_PAGE;
    const end = start + PER_PAGE;
    const pageItems = state.allAlumni.slice(start, end);
    
    renderAlumniList(pageItems);

    // Client pagination UI
    const container = document.getElementById('pagination');
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    let html = '<nav><ul class="pagination pagination-sm justify-content-center">';
    for (let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item ${i === page ? 'active' : ''}">
            <a class="page-link" href="#" onclick="goToClientPage(${i}); return false;">${i}</a>
        </li>`;
    }
    html += '</ul></nav>';
    container.innerHTML = html;
}

function goToClientPage(page) {
    state.isSearchActive = false;
    state.searchParams = {};
    renderClientAlumniPage(page);
    // Keep map markers for all alumni
    placeAlumniMarkers(state.allAlumni, (alumnus) => openJobsModal(alumnus));
    // Clear search fields
    document.getElementById('searchForm').reset();
}

// ============== SEARCH ==============
async function handleSearch(e) {
    e.preventDefault();
    state.isSearchActive = true;
    state.searchParams = {
        name: document.getElementById('searchName').value,
        enrollment_year: document.getElementById('searchEnrollmentYear').value,
        graduation_year: document.getElementById('searchGraduationYear').value,
        country: document.getElementById('searchCountry').value,
        format: 'json'
    };
    state.currentPage = 1;
    await performSearch();
}

async function performSearch() {
    const params = { ...state.searchParams, page: state.currentPage.toString() };
    const result = await apiSearchAlumni(params, state.token);

    if (result.ok) {
        const data = result.data;
        const results = data.data || [];
        
        renderAlumniList(results);
        renderPagination(data.pagination);
        
        // Update map with search results
        placeAlumniMarkers(results, (alumnus) => openJobsModal(alumnus));
        
        // Chart remains static - always shows ALL alumni distribution
        // (the chart is a summary of all graduates, not filtered)
    } else {
        // Gracefully handle search failure / empty results without popping up an error toast
        renderAlumniList([]);
        renderPagination(null);
        placeAlumniMarkers([], (alumnus) => openJobsModal(alumnus));
    }
}

function renderPagination(pagination) {
    const container = document.getElementById('pagination');
    if (!container) return;

    if (!pagination || pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '<nav><ul class="pagination pagination-sm justify-content-center">';
    
    for (let i = 1; i <= pagination.total_pages; i++) {
        html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
        </li>`;
    }
    
    html += '</ul></nav>';
    container.innerHTML = html;
}

async function goToPage(page) {
    state.currentPage = page;
    await performSearch();
}

// ============== JOBS MODAL ==============
async function openJobsModal(alumnus) {
    state.selectedAlumnus = alumnus;
    
    const nameEl = document.getElementById('jobsModalLabel');
    const contentEl = document.getElementById('jobsContent');
    const addBtn = document.getElementById('addJobBtn');
    
    nameEl.textContent = `${alumnus.first_name} ${alumnus.last_name}`;
    contentEl.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    // Authorization: check if logged-in user is the alumnus owner
    const isOwner = state.token && state.currentUser && state.currentUser.id === alumnus.id;
    
    // Show add button only if logged in AND is the owner
    addBtn.style.display = isOwner ? 'block' : 'none';
     
    showModal('jobsModal');
    
    const result = await apiGetAlumnusJobs(alumnus.id, state.token);
    
    if (result.ok && result.data.status === 'success') {
        const jobs = result.data.data || [];
        
        if (jobs.length === 0) {
            contentEl.innerHTML = '<div class="text-center text-muted p-4"><i class="bi bi-briefcase"></i> No jobs listed</div>';
        } else {
            contentEl.innerHTML = jobs.map((job, index) => `
                <div class="modal-job-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="job-company">${job.company_name}</div>
                            <div class="job-title">${job.job_title}</div>
                            <div class="job-location">${job.city}, ${job.country}</div>
                            <small class="text-muted">Since: ${job.start_date}${job.is_current ? ' <span class="badge bg-success">Current</span>' : ''}</small>
                        </div>
                        ${isOwner ? `
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="editJob(${alumnus.id}, ${job.id})" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteJob(${alumnus.id}, ${job.id})" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        }
    } else {
        contentEl.innerHTML = '<div class="text-center text-danger p-4">Failed to load jobs</div>';
    }
}

// ============== JOBS CRUD ==============
function showJobFormModal(alumnusId, jobId = null) {
    const modalLabel = document.getElementById('jobFormModalLabel');
    const form = document.getElementById('jobForm');
    const jobIdField = document.getElementById('jobFormJobId');
    const alumnusIdField = document.getElementById('jobFormAlumnusId');

    if (jobId) {
        modalLabel.textContent = 'Edit Job';
    } else {
        modalLabel.textContent = 'Add New Job';
        form.reset();
    }

    jobIdField.value = jobId || '';
    alumnusIdField.value = alumnusId || '';

    showModal('jobFormModal');
}

async function editJob(alumnusId, jobId) {
    // Load current job data
    const result = await apiGetAlumnusJobs(alumnusId, state.token);
    if (result.ok && result.data.data) {
        const job = result.data.data.find(j => j.id === jobId);
        if (job) {
            document.getElementById('jobFormCompany').value = job.company_name;
            document.getElementById('jobFormTitle').value = job.job_title;
            document.getElementById('jobFormCountry').value = job.country;
            document.getElementById('jobFormCity').value = job.city;
            document.getElementById('jobFormLatitude').value = job.latitude;
            document.getElementById('jobFormLongitude').value = job.longitude;
            document.getElementById('jobFormStartDate').value = job.start_date;
            document.getElementById('jobFormIsCurrent').checked = job.is_current === '1' || job.is_current === 1;
        }
    }
    showJobFormModal(alumnusId, jobId);
}

async function deleteJob(alumnusId, jobId) {
    if (!confirm('Are you sure you want to delete this job?')) return;
    
    const result = await apiDeleteJob(alumnusId, jobId, state.token);
    
    if (result.ok) {
        showToast('Job deleted successfully!', 'success');
        openJobsModal(state.selectedAlumnus);
        await loadAllData();
    } else {
        showToast(result.data.message || 'Failed to delete job', 'danger');
    }
}

async function handleJobFormSubmit(e) {
    e.preventDefault();
    
    const alumnusId = parseInt(document.getElementById('jobFormAlumnusId').value);
    const jobId = document.getElementById('jobFormJobId').value;
    const isEdit = jobId !== '';
    
    const data = {
        company_name: document.getElementById('jobFormCompany').value,
        job_title: document.getElementById('jobFormTitle').value,
        country: document.getElementById('jobFormCountry').value,
        city: document.getElementById('jobFormCity').value,
        latitude: parseFloat(document.getElementById('jobFormLatitude').value),
        longitude: parseFloat(document.getElementById('jobFormLongitude').value),
        start_date: document.getElementById('jobFormStartDate').value,
        is_current: document.getElementById('jobFormIsCurrent').checked ? 1 : 0,
    };

    const result = isEdit
        ? await apiUpdateJob(alumnusId, parseInt(jobId), data, state.token)
        : await apiCreateJob(alumnusId, data, state.token);

    if (result && result.ok) {
        hideModal('jobFormModal');
        showToast(`Job ${isEdit ? 'updated' : 'created'} successfully!`, 'success');
        openJobsModal(state.selectedAlumnus);
        await loadAllData();
    } else {
        showToast(result?.data?.message || 'Failed to save job', 'danger');
    }
}

// ============== STATS ==============
async function updateStats() {
    const el = document.getElementById('statsBar');
    if (!el) return;
    
    if (state.allAlumni.length > 0) {
        el.innerHTML = `
            <span class="badge bg-primary me-2"><i class="bi bi-people"></i> ${state.allAlumni.length} Alumni</span>
            <span class="badge bg-success me-2"><i class="bi bi-geo-alt"></i> ${state.allAlumni.filter(a => a.jobs && a.jobs.length > 0).length} Employed</span>
            <span class="badge bg-info"><i class="bi bi-globe"></i> Tracking worldwide</span>
        `;
    }
}

// ============== TOAST NOTIFICATIONS ==============
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toastId = 'toast-' + Date.now();
    const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
    
    container.innerHTML = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi ${icon} me-2"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    const toastEl = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();

    // Remove after hidden
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}