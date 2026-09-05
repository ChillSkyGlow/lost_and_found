import { getListings } from '../api/index.js';
import { getMessages } from '../api/index.js';
import { sanitizeHTML } from '../utils/sanitize.js';
import { $, $$ } from '../utils/dom.js';
import { checkSessionAndSetupHeader } from './auth.js';
import { initializeMap } from '../utils/map.js';

let map, searchCenter, searchRadiusCircle;
let currentPage = 1;
let currentFilter = 'all';
let currentSearchTerm = '';
let currentGeoFilter = null;
let currentSort = 'time_desc'; // 默认按时间降序

// 页面加载时检查会话状态并设置导航栏
document.addEventListener('DOMContentLoaded', async () => {
    await checkSessionAndSetupHeader();
    // 匹配物品铃铛提示逻辑
    const matchNotification = document.getElementById("match-notification");
    function checkMatchedNotification() {
        getMessages().then(result => {
            if (result.success && result.data && result.data.length > 0) {
                matchNotification.textContent = "你有新的消息待查看！";
            } else {
                matchNotification.textContent = "您暂时没有新的消息";
            }
        }).catch(() => {
            matchNotification.textContent = "您暂时没有新的消息";
        });
    }
    if (matchNotification) {
        matchNotification.addEventListener("click", () => {
            window.location.href = "messages.html";
        });
    }
    checkMatchedNotification();
});

async function displayListings(page = 1) {
    const listingsContainer = $('#listings-wall');
    if (!listingsContainer) return;

    currentPage = page;

    listingsContainer.innerHTML = '<p>正在加载...</p>';
    $('#pagination-container').innerHTML = '';

    try {
        const params = {
            filter: currentFilter,
            search: currentSearchTerm,
            page: currentPage,
            sort: currentSort,
            date: $('#search-date').value,
            category: $('#search-category').value,
            location: $('#search-location').value.trim()
        };
        
        // 确保分页时也能保留地理筛选
        if (currentGeoFilter && !params.lat) {
            params.lat = currentGeoFilter.lat;
            params.lon = currentGeoFilter.lon;
            params.radius = currentGeoFilter.radius;
        }

        const { listings, pagination } = await getListings(params);

        if (!listings || listings.length === 0) {
            listingsContainer.innerHTML = '<p>未找到相关物品。</p>';
            return;
        }

        const listingsHTML = listings.map(listing => {
            const isLost = listing.listing_type === 'lost';
            const cardClass = isLost ? 'lost' : 'found';
            const tagText = isLost ? '失物' : '招领';

            return `
                <div class="card ${cardClass}" data-id="${listing.listing_id}" data-type="${listing.listing_type}">
                    <div class="card-tag ${cardClass}-tag">${tagText}</div>
                    <img src="${listing.image_file_path ? '../backend/' + sanitizeHTML(listing.image_file_path) : 'images/default.png'}" alt="${sanitizeHTML(listing.item_name)}">
                    <div class="card-content">
                        <h3>${sanitizeHTML(listing.item_name)}</h3>
                        <p><strong>类别:</strong> ${sanitizeHTML(listing.category) || '无'}</p>
                        <p><strong>描述:</strong> ${sanitizeHTML(listing.description) || '无'}</p>
                        <p><strong>时间:</strong> ${new Date(listing.event_time).toLocaleString()}</p>
                        <p><strong>地点:</strong> ${sanitizeHTML(listing.location_details)}</p>
                    </div>
                    <div class="card-footer">
                        <span>发布者: ${sanitizeHTML(listing.username)}</span>
                        <span>${new Date(listing.created_at).toLocaleDateString()}</span>
                    </div>
                </div>
            `;
        }).join('');

        listingsContainer.innerHTML = listingsHTML;
        
        renderPagination(pagination);

        $$('.card').forEach(item => {
            item.addEventListener('click', () => {
                const listingId = item.dataset.id;
                const listingType = item.dataset.type;
                window.location.href = `details.html?id=${listingId}&type=${listingType}`;
            });
        });

    } catch (error) {
        console.error('无法加载物品列表:', error);
        listingsContainer.innerHTML = '<p>加载物品列表失败，请稍后再试。</p>';
    }
}

function renderPagination({ currentPage, totalPages }) {
    const paginationContainer = $('#pagination-container');
    if (!paginationContainer || totalPages <= 1) return;

    let paginationHTML = '';

    if (currentPage > 1) {
        paginationHTML += `<a href="#" data-page="${currentPage - 1}">&laquo; 上一页</a>`;
    } else {
        paginationHTML += `<span class="disabled">&laquo; 上一页</span>`;
    }

    for (let i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
            paginationHTML += `<span class="current-page">${i}</span>`;
        } else {
            paginationHTML += `<a href="#" data-page="${i}">${i}</a>`;
        }
    }

    if (currentPage < totalPages) {
        paginationHTML += `<a href="#" data-page="${currentPage + 1}">下一页 &raquo;</a>`;
    } else {
        paginationHTML += `<span class="disabled">下一页 &raquo;</span>`;
    }

    paginationContainer.innerHTML = paginationHTML;

    $$('#pagination-container a').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = parseInt(e.target.dataset.page);
            displayListings(page);
            window.scrollTo(0, 0);
        });
    });
}

function setupEventListeners() {
    const searchForm = $('#search-form');
    const searchInput = $('#search-keyword');
    const filterButtons = $$('.filter-btn');
    const mapFilterModal = $('#map-filter-modal');
    const openMapBtn = $('#map-filter-btn');
    const closeMapBtn = $('.close-btn');
    const applyMapFilterBtn = $('#apply-map-filter-btn');
    const clearMapFilterBtn = $('#clear-map-filter-btn');

    const performSearch = () => {
        currentSearchTerm = searchInput.value.trim();
        currentFilter = $$('.filter-btn.active[data-filter-type="type"]')[0]?.dataset.value || 'all';
        // sort 值在按钮点击时已经更新到 currentSort, 这里无需重复获取
        displayListings(1); // 重新搜索总是从第一页开始
    };

    if(searchForm) {
        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            performSearch();
        });
    }

    filterButtons.forEach(button => {
        if(button.id === 'map-filter-btn') return;
        
        button.addEventListener('click', () => {
            const filterType = button.dataset.filterType; // 'type' 或 'sort'
            const value = button.dataset.value;

            // 将同类型的按钮的 active 状态清除
            $$(`.filter-btn[data-filter-type="${filterType}"]`).forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            if (filterType === 'type') {
                currentFilter = value;
            } else if (filterType === 'sort') {
                currentSort = value;
            }
            
            performSearch();
        });
    });

    let marker, radiusCircle;

    if (openMapBtn) {
        openMapBtn.addEventListener('click', () => {
            mapFilterModal.style.display = 'block';
            if (!map) {
                map = initializeMap('modal-map-container', {
                    onClick: (e, mapInstance) => {
                        const lnglat = e.lnglat;
                        $('#selected-coords-display').textContent = `${lnglat.lat.toFixed(6)}, ${lnglat.lng.toFixed(6)}`;
                        
                        if (marker) {
                            marker.setLngLat(lnglat);
                            radiusCircle.setCenter(lnglat);
                        } else {
                            marker = new T.Marker(lnglat);
                            mapInstance.addOverLay(marker);
                            radiusCircle = new T.Circle(lnglat, parseFloat($('#search-radius').value) * 1000, {
                                color: "blue", weight: 2, opacity: 0.5, fillColor: "blue", fillOpacity: 0.2
                            });
                            mapInstance.addOverLay(radiusCircle);
                        }
                    }
                });
            }
        });
    }

    if (closeMapBtn) {
        closeMapBtn.addEventListener('click', () => mapFilterModal.style.display = 'none');
    }

    if (applyMapFilterBtn) {
        applyMapFilterBtn.addEventListener('click', () => {
            if (!marker) {
                alert('请先在地图上选择一个中心点。');
                return;
            }
            currentGeoFilter = {
                lat: marker.getLngLat().lat,
                lon: marker.getLngLat().lng,
                radius: $('#search-radius').value
            };
            mapFilterModal.style.display = 'none';
            openMapBtn.classList.add('active');
            openMapBtn.textContent = '已按地图位置筛选';
            performSearch();
        });
    }
    
    if (clearMapFilterBtn) {
        clearMapFilterBtn.addEventListener('click', () => {
            currentGeoFilter = null;
            if (marker) {
                map.removeOverLay(marker);
                marker = null;
            }
            if (radiusCircle) {
                map.removeOverLay(radiusCircle);
                radiusCircle = null;
            }
            $('#selected-coords-display').textContent = '未选择';
            mapFilterModal.style.display = 'none';
            openMapBtn.classList.remove('active');
            openMapBtn.textContent = '按地图筛选';
            performSearch();
        });
    }
    
    window.addEventListener('click', (event) => {
        if (event.target == mapFilterModal) {
            mapFilterModal.style.display = 'none';
        }
    });

    $('#search-radius')?.addEventListener('input', (e) => {
        if (radiusCircle) {
            const radiusInKm = parseFloat(e.target.value);
            if(radiusInKm > 0){
                radiusCircle.setRadius(radiusInKm * 1000);
            }
        }
    });
}


document.addEventListener('DOMContentLoaded', () => {
    checkSessionAndSetupHeader();
    displayListings();
    setupEventListeners();
}); 