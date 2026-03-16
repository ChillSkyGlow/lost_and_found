import { $, $$ } from '../utils/dom.js';
import { getListingDetails, updateListing } from '../api/index.js';
import { checkSessionAndSetupHeader } from './auth.js';
import { initializeMap } from '../utils/map.js';

// --- 天地图API异步加载器 ---
function loadTiandituApi() {
    return new Promise((resolve, reject) => {
        if (window.T) return resolve(window.T);
        const script = document.createElement('script');
        // 使用天地图API,将YOUR_API改为你的API密钥
        // 注意：请替换为你自己的API密钥
        script.src = 'https://api.tianditu.gov.cn/api?v=4.0&tk=YOUR_API';
        script.onload = () => window.T ? resolve(window.T) : reject(new Error('天地图 API 加载错误'));
        script.onerror = () => reject(new Error('无法加载天地图 API'));
        document.head.appendChild(script);
    });
}

async function loadListingData(id, type) {
    const messageBox = $('#edit-message-box');
    try {
        // 先加载地图API，再初始化地图
        await loadTiandituApi();

        const result = await getListingDetails(id, type);
        if (!result.success || !result.data.details) {
            messageBox.innerHTML = '<p class="message error">未找到该物品信息。</p>';
            return;
        }
        const listing = result.data.details;

        // 填充表单
        $('#listing_id').value = listing.id;
        $('#type').value = listing.listing_type;
        $('#type-display').textContent = listing.listing_type === 'lost' ? '失物信息' : '拾物信息';
        $('#item-name').value = listing.title;
        $('#description').value = listing.description;
        $('#location-details').value = listing.location_details;
        $('#location-coordinates').value = listing.location_coords;
        
        const eventTime = new Date(listing.event_time);
        const timezoneOffset = eventTime.getTimezoneOffset() * 60000;
        const localISOTime = new Date(eventTime - timezoneOffset).toISOString().slice(0, 16);
        $('#event-time').value = localISOTime;
        
        if(listing.image_path) {
            $('#current-image').src = `../backend/${listing.image_path}`;
            $('#current-image').style.display = 'block';
        } else {
            $('#current-image').style.display = 'none';
        }

        initializeMap('map-container', { 
            inputId: 'location-coordinates', 
            initialCoords: listing.location_coords
        });

    } catch (error) {
        console.error('无法加载物品详情:', error);
        messageBox.innerHTML = '<p class="message error">无法加载物品详情，请返回重试。</p>';
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    // 必须等待会话检查完成，因为它可能会影响页面状态
    await checkSessionAndSetupHeader(true); // 添加 await，并要求登录

    const urlParams = new URLSearchParams(window.location.search);
    const listingId = urlParams.get('id');
    const listingType = urlParams.get('type');
    
    const formContainer = $('#edit-form-container'); // 虽然HTML里没有，但以防万一
    const messageBox = $('#edit-message-box');

    if (!listingId || !listingType) {
        const targetElement = formContainer || messageBox.parentElement;
        targetElement.innerHTML = '<p class="message error">无效的链接：缺少物品ID或类型。</p>';
        return;
    }

    loadListingData(listingId, listingType);

    $('#edit-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const submitButton = $('#edit-form button[type="submit"]');
        const originalButtonText = submitButton.textContent;
        
        submitButton.disabled = true;
        submitButton.textContent = '更新中...';

        try {
            const formDataObj = Object.fromEntries(formData.entries());
            // 前端传 'title'，后端用映射处理
            formDataObj.title = formDataObj.item_name;
            // delete formDataObj.item_name; // 可选

            const result = await updateListing(formData);
            if (result.success) {
                messageBox.innerHTML = '<p class="message success">更新成功！2秒后将返回个人中心...</p>';
                setTimeout(() => window.location.href = 'profile.html', 2000);
            } else {
                messageBox.innerHTML = `<p class="message error">${result.message || '发生未知错误'}</p>`;
            }
        } catch (error) {
            messageBox.innerHTML = `<p class="message error">${error.message || '网络请求失败，请稍后重试。'}</p>`;
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
        }
    });
}); 