import { $, $$ } from '../utils/dom.js';
import { publishListing } from '../api/index.js';
import { showMessage } from '../utils/dom.js';
import { checkSessionAndSetupHeader } from './auth.js';
import { initializeMap } from '../utils/map.js';

// --- 文案映射表：失物视角 vs 招领视角（功能3：招领信息发布动态化）---
const UI_TEXT = {
  lost: {
    descriptionLabel: '详细描述（物品特征、丢失经过等）',
    descriptionPlaceholder: '请详细描述物品的外观、颜色、特殊标记、丢失经过、可能丢失的具体位置等信息，便于他人辨认。',
    locationDetailsLabel: '丢失地点详情',
    mapLabel: '在地图上标记丢失位置（请点击地图）',
    eventTimeLabel: '丢失时间'
  },
  found: {
    descriptionLabel: '详细描述（物品特征、拾获经过、保管方式等）',
    descriptionPlaceholder: '请详细描述物品的外观、颜色、特殊标记、拾获具体时间地点、当前保管方式等信息，便于失主核实。',
    locationDetailsLabel: '拾获地点详情',
    mapLabel: '在地图上标记拾获位置（请点击地图）',
    eventTimeLabel: '拾获时间'
  }
};

// --- 切换发布视角文案：失物/招领 ---
function switchListingType(type) {
  const text = UI_TEXT[type] || UI_TEXT.lost;
  const descLabel = $('#description-label');
  const descEl = $('#description');
  const locLabel = $('#location-details-label');
  const mapLabel = $('#map-label');
  const timeLabel = $('#event-time-label');

  if (descLabel) descLabel.textContent = text.descriptionLabel;
  if (descEl) descEl.placeholder = text.descriptionPlaceholder;
  if (locLabel) locLabel.textContent = text.locationDetailsLabel;
  if (mapLabel) mapLabel.textContent = text.mapLabel;
  if (timeLabel) timeLabel.textContent = text.eventTimeLabel;
}

// --- 天地图API异步加载器 ---
function loadTiandituApi() {
    return new Promise((resolve, reject) => {
        // 如果API已经加载，则直接返回
        if (window.T) {
            return resolve(window.T);
        }
        
        const script = document.createElement('script');
        // 使用天地图API,将YOUR_API改为你的API密钥
        // 注意：请替换为你自己的API密钥
        script.src = 'https://api.tianditu.gov.cn/api?v=4.0&tk=YOUR_API'; 
        script.onload = () => {
            if (window.T) {
                resolve(window.T);
            } else {
                reject(new Error('天地图 API 加载成功但 T 对象未定义'));
            }
        };
        script.onerror = () => reject(new Error('无法加载天地图 API'));
        document.head.appendChild(script);
    });
}

// --- 添加测试诊断功能 ---
async function testPublishAPI() {
    const messageBox = $('#publish-message-box');
    showMessage(messageBox, 'info', '正在测试API连接...');
    
    try {
        // 直接使用fetch而不是封装的API调用，以便更清楚地看到错误
        const formData = new FormData();
        formData.append('test', 'value');
        
        const response = await fetch('../backend/api/listings/test_publish.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        
        // 获取原始响应文本
        const responseText = await response.text();
        showMessage(messageBox, 'info', '收到的原始响应: ' + responseText.substring(0, 300));
        
        // 尝试解析为JSON
        try {
            const result = JSON.parse(responseText);
            showMessage(messageBox, 'success', '测试成功！服务器返回了有效的JSON响应。');
            console.log('API测试结果:', result);
        } catch (e) {
            showMessage(messageBox, 'error', '服务器返回了无效的JSON。这可能是PHP错误或配置问题。');
            console.error('无效的JSON响应:', responseText);
        }
    } catch (error) {
        showMessage(messageBox, 'error', '测试失败: ' + error.message);
    }
}


document.addEventListener('DOMContentLoaded', async () => {
    await checkSessionAndSetupHeader();
    
    // --- 功能3：初始化视角文案 + radio 切换监听 ---
    const typeLost = $('#type-lost');
    const typeFound = $('#type-found');
    const applyInitial = () => {
      if (typeLost && typeLost.checked) switchListingType('lost');
      else if (typeFound && typeFound.checked) switchListingType('found');
    };
    applyInitial();
    if (typeLost) typeLost.addEventListener('change', () => typeLost.checked && switchListingType('lost'));
    if (typeFound) typeFound.addEventListener('change', () => typeFound.checked && switchListingType('found'));

    try {
        await loadTiandituApi();
        // API加载成功后才初始化地图
        initializeMap('map-container', { inputId: 'location-coordinates' });
    } catch (error) {
        console.error(error);
        // 可以在这里向用户显示地图加载失败的消息
        const mapContainer = $('#map-container');
        if(mapContainer) {
            mapContainer.innerHTML = '<p style="text-align:center; color: red;">地图服务加载失败，请刷新页面重试。</p>';
        }
    }

    const publishForm = $('#publish-form');
    if (!publishForm) return;

    publishForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // 验证地图坐标是否已选择
        const locationCoords = $('#location-coordinates').value;
        if (!locationCoords) {
            showMessage($('#publish-message-box'), 'error', '请在地图上标记物品的位置');
            return;
        }

        const formData = new FormData(publishForm);
        const submitButton = $('#publish-submit-btn');
        const messageBox = $('#publish-message-box');
        
        submitButton.disabled = true;
        submitButton.textContent = '发布中...';

        try {
            // 修改为使用直接的fetch调用并显示原始响应
            try {
                // 使用新的更强健的发布处理脚本
                const response = await fetch('../backend/api/listings/publish_secure.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });
                
                const responseText = await response.text();
                console.log('原始响应:', responseText);
                
                try {
                    const result = JSON.parse(responseText);
                    if (result.success) {
                        showMessage(messageBox, 'success', '发布成功！2秒后将跳转到主页...');
                        setTimeout(() => {
                            window.location.href = 'index.html';
                        }, 2000);
                    } else {
                        showMessage(messageBox, 'error', result.message || '发生未知错误');
                    }
                } catch (e) {
                    showMessage(messageBox, 'error', '服务器返回了无效的JSON响应。可能的PHP错误: ' + responseText.substring(0, 100));
                    console.error('无效的JSON响应:', responseText);
                }
            } catch (fetchError) {
                showMessage(messageBox, 'error', '网络请求失败: ' + fetchError.message);
            }
        } catch (error) {
            showMessage(messageBox, 'error', error.message || '网络请求失败，请稍后重试。');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = '确认发布';
        }
    });
}); 