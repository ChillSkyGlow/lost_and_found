/**
 * 初始化一个可交互的地图（用于发布和修改页面）
 * @param {string} containerId - 地图容器的ID
 * @param {object} options - 配置对象
 * @param {function(event, map): void} [options.onClick] - 地图点击事件的回调函数, 会收到事件对象和地图实例
 * @param {string} [options.inputId] - 存储坐标的隐藏输入框的ID
 * @param {string} [options.initialCoords] - 初始坐标 "lng,lat"
 */
export function initializeMap(containerId, options = {}) {
    const { inputId, initialCoords, onClick } = options;
    const mapContainer = document.getElementById(containerId);

    if (!mapContainer) {
        console.error(`Map container with id #${containerId} not found.`);
        return null;
    }

    // 检查天地图API是否加载
    if (typeof T === 'undefined') {
        mapContainer.innerHTML = '<p style="text-align:center; color: red;">地图服务不可用，请检查网络连接或刷新页面。</p>';
        console.error('Tianditu API (T object) is not loaded.');
        return null; // 优雅地退出
    }

    const coordsInput = inputId ? document.getElementById(inputId) : null;
    
    // 默认中心点设为某个校园（例如：北京邮电大学）
    let centerPoint = new T.LngLat(116.284343, 40.156252);
    let initialZoom = 16;
    let marker = null;

    if (initialCoords) {
        const [lng, lat] = initialCoords.split(',');
        if (!isNaN(parseFloat(lng)) && !isNaN(parseFloat(lat))) {
            centerPoint = new T.LngLat(parseFloat(lng), parseFloat(lat));
            initialZoom = 17; // 如果有坐标，放大级别更高
        }
    }

    const map = new T.Map(containerId);
    map.centerAndZoom(centerPoint, initialZoom);
    map.setMapType(TMAP_HYBRID_MAP); // 使用卫星混合图，更直观

    // 如果有初始坐标，先放置一个标记
    if (initialCoords) {
        marker = new T.Marker(centerPoint);
        map.addOverLay(marker);
    }

    // 监听地图点击事件
    map.addEventListener("click", (e) => {
        // 如果提供了自定义的onClick回调，则执行它
        if (onClick) {
            onClick(e, map); // 将map实例也传递回去
            return;
        }

        // --- 如果没有提供onClick，则执行默认行为 ---
        // 先清除旧的标记
        if (marker) {
            map.removeOverLay(marker);
        }
        
        const lnglat = e.lnglat;
        // 创建新标记
        marker = new T.Marker(lnglat);
        map.addOverLay(marker);
        
        // 更新隐藏输入框的值
        if (coordsInput) {
            coordsInput.value = `${lnglat.getLng()},${lnglat.getLat()}`;
        }
    });

    return map;
}

/**
 * 初始化一个只显示的地图（用于详情页面）
 * @param {string} containerId - 地图容器的ID
 * @param {string} coordsString - 坐标字符串 "lng,lat"
 */
export function initDisplayMap(containerId, coordsString) {
    const mapContainer = document.getElementById(containerId);
    if (!mapContainer) return;
    
    if (!coordsString) {
        mapContainer.innerHTML = '<p class="text-center py-8 text-gray-500">未提供地图位置</p>';
        return;
    }

    const [lng, lat] = coordsString.split(',');
    if (isNaN(parseFloat(lng)) || isNaN(parseFloat(lat))) {
        mapContainer.innerHTML = '<p class="text-center py-8 text-gray-500">无效的地图坐标</p>';
        return;
    }

    const centerPoint = new T.LngLat(parseFloat(lng), parseFloat(lat));

    const map = new T.Map(containerId);
    map.centerAndZoom(centerPoint, 17); // 放大级别高一些
    map.setMapType(TMAP_HYBRID_MAP);

    // 禁用地图交互
    map.disableDoubleClickZoom();
    map.disableKeyboard();
    
    // 添加标记
    const marker = new T.Marker(centerPoint);
    map.addOverLay(marker);
} 