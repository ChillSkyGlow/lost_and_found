// --- API Interaction Module ---

const API_BASE_URL = '../backend/api';

/**
 * A generic fetch wrapper that automatically includes credentials
 * and sets the correct headers for JSON or FormData POST/PUT requests.
 * @param {string} url - The endpoint URL relative to API_BASE_URL.
 * @param {object} options - Standard fetch options.
 * @returns {Promise<any>} - The JSON response from the API.
 */
async function apiCall(url, options = {}) {
    // Default to including credentials for session handling
    options.credentials = 'include';

    // Set JSON content type for non-FormData POST/PUT requests
    if (['POST', 'PUT'].includes(options.method) && options.body && !(options.body instanceof FormData)) {
        options.headers = {
            'Content-Type': 'application/json',
            ...options.headers,
        };
    }

    try {
        const response = await fetch(`${API_BASE_URL}${url}`, options);
        
        // 获取响应文本
        const responseText = await response.text();
        
        // 尝试解析为JSON
        let responseJson;
        try {
            responseJson = JSON.parse(responseText);
        } catch (e) {
            // 如果不是有效的JSON，输出原始响应并抛出错误
            console.error('Invalid JSON response:', responseText);
            throw new Error(`服务器返回了无效的数据格式。可能是PHP错误: ${responseText.substring(0, 100)}...`);
        }
        
        // 检查是否为401未授权，如果是，则自动跳转到登录页面
        if (response.status === 401) {
            alert('您需要登录才能执行此操作');
            window.location.href = 'login.html';
            throw new Error('请先登录'); // 抛出错误阻止后续操作
        }
        
        // The ok property checks for HTTP status codes in the 200-299 range.
        if (!response.ok) {
            console.error('API Error:', response.status, responseJson);
            // Throw an error that includes the structured message from the backend if available
            throw new Error(responseJson.message || `HTTP错误! 状态码: ${response.status}`);
        }
        
        return responseJson;
    } catch (error) {
        console.error('Fetch failed:', error);
        // Re-throw the error so the calling function's catch block can handle it
        throw error;
    }
}

// Helper function to convert an object to FormData
function objectToFormData(obj) {
    const formData = new FormData();
    for (const key in obj) {
        if (obj.hasOwnProperty(key)) {
            formData.append(key, obj[key]);
        }
    }
    return formData;
}

// --- Auth & User Endpoints ---
export const checkSession = () => apiCall('/auth/check_session.php', { method: 'GET' });
export const login = (credentials) => apiCall('/auth/login.php', { method: 'POST', body: objectToFormData(credentials) });
export const logout = () => apiCall('/auth/logout.php', { method: 'POST' });
export const register = (userData) => apiCall('/users/register.php', { method: 'POST', body: objectToFormData(userData) });
export const getUserInfo = () => apiCall('/users/get_user_info.php', { method: 'GET' });
export const updateProfile = (profileData) => apiCall('/users/update_profile.php', { method: 'POST', body: objectToFormData(profileData) });
export const changePassword = (passwordData) => apiCall('/users/change_password.php', { method: 'POST', body: objectToFormData(passwordData) });
export const changeSecurityQuestion = (securityData) => apiCall('/users/change_security_question.php', { method: 'POST', body: objectToFormData(securityData) });
export const forgotPasswordStep1 = (emailData) => apiCall('/users/forgot_password_step1.php', { method: 'POST', body: objectToFormData(emailData) });
export const forgotPasswordStep2 = (resetData) => apiCall('/users/forgot_password_step2.php', { method: 'POST', body: objectToFormData(resetData) });

// --- Listing Endpoints ---
export const getMyListings = () => apiCall('/users/get_my_listings.php', { method: 'GET' });
export const getMatchedListings = () => apiCall('/listings/get_matched_listings.php', { method: 'GET' });

/**
 * Fetches listings based on provided filters.
 * @param {object} params - An object of query parameters (e.g., { filter: 'lost', search: 'keys' }).
 * @returns {Promise<any>}
 */
export const getListings = (params = {}) => {
    // Convert the params object into a URL query string
    const queryParams = new URLSearchParams(params).toString();
    return apiCall(`/listings/get_listings.php?${queryParams}`, { method: 'GET' });
};

export const getListingDetails = (id, type) => apiCall(`/listings/get_listing_details.php?id=${id}&type=${type}`, { method: 'GET' });
export const publishListing = (formData) => apiCall('/listings/publish_listing.php', { method: 'POST', body: formData });
export const updateListing = (formData) => apiCall('/listings/update_listing.php', { method: 'POST', body: formData });
export const updateListingStatus = (listing_id, type) => apiCall('/listings/update_listing_status.php', { method: 'POST', body: objectToFormData({ listing_id, type }) });
export const deleteListing = (listing_id, type) => apiCall('/listings/delete_listing.php', { method: 'POST', body: objectToFormData({ listing_id, type }) });

// --- Claim / Solve Endpoints（功能6：认领申请） ---
export const submitClaim = (payload) => apiCall('/listings/submit_claim.php', { method: 'POST', body: objectToFormData(payload) });
export const getClaimsForMyFound = (found_listing_id = null) => {
    const q = found_listing_id ? `?found_listing_id=${encodeURIComponent(found_listing_id)}` : '';
    return apiCall(`/listings/get_claims_for_my_found.php${q}`, { method: 'GET' });
};
export const getMyClaims = () => apiCall('/listings/get_my_claims.php', { method: 'GET' });

// --- Comment Endpoints ---
export const postComment = (listingId, content, type) => apiCall('/comments/post_comment.php', { method: 'POST', body: objectToFormData({ listing_id: listingId, content, type }) });

// --- Message Endpoints ---
export const getMessages = () => apiCall('/users/get_messages.php', { method: 'GET' }); 