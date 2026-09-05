import { getMyListings, deleteListing, getMyClaims } from "../api/index.js";
import { getMatchedListings } from "../api/index.js";
import { checkSessionAndSetupHeader } from "./auth.js";

document.addEventListener("DOMContentLoaded", async () => {
    const session = await checkSessionAndSetupHeader();
    if (!session) return;

    const lostPendingList = document.getElementById("lost-pending-list");
    const lostSolvedList = document.getElementById("lost-solved-list");
    const foundUnclaimedList = document.getElementById("found-unclaimed-list");
    const foundClaimedList = document.getElementById("found-claimed-list");
    const matchedList = document.getElementById("matched-list");
    const myClaimsList = document.getElementById("my-claims-list");
    const matchNotification = document.getElementById("match-notification");
    const matchDot = document.getElementById("match-dot");

    const renderCard = (item, isMatched = false) => {
        const card = document.createElement('div');
        card.className = 'card listing-card';
        card.dataset.id = item.id;
        card.dataset.type = item.listing_type;

        const imageUrl = item.image_path ? `../backend/${item.image_path}` : "images/default.png";
        
        const img = document.createElement('img');
        img.src = imageUrl;
        img.alt = item.title;
        img.className = 'card-image';
        card.appendChild(img);

        const cardContent = document.createElement('div');
        cardContent.className = 'card-content';
        
        const title = document.createElement('h3');
        title.className = 'card-title';
        title.textContent = item.title;
        cardContent.appendChild(title);

        const date = document.createElement('p');
        date.className = 'card-date';
        date.textContent = `发布于: ${new Date(item.created_at).toLocaleDateString()}`;
        cardContent.appendChild(date);

        const cardActions = document.createElement('div');
        cardActions.className = 'card-actions';

        const viewLink = document.createElement('a');
        viewLink.href = `details.html?id=${item.id}&type=${item.listing_type}`;
        viewLink.className = 'action-btn btn-view';
        viewLink.textContent = '查看详情';
        cardActions.appendChild(viewLink);

        if (!isMatched) {
        if (item.status === 'pending' || item.status === 'unclaimed') {
            const editLink = document.createElement('a');
            editLink.href = `edit_listing.html?id=${item.id}&type=${item.listing_type}`;
            editLink.className = 'action-btn btn-edit';
            editLink.textContent = '编辑';
            cardActions.appendChild(editLink);
        }
            // 只有未完成的帖子才显示删除按钮
            if (item.status === 'pending' || item.status === 'unclaimed') {
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'action-btn btn-delete';
        deleteBtn.dataset.id = item.id;
        deleteBtn.textContent = '删除';
        cardActions.appendChild(deleteBtn);
            }
        }

        cardContent.appendChild(cardActions);
        card.appendChild(cardContent);

        return card;
    };

    const addCardClickListeners = () => {
        document.querySelectorAll('.listing-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('.action-btn')) {
                    return;
                }
                const id = card.dataset.id;
                const type = card.dataset.type;
                window.location.href = `details.html?id=${id}&type=${type}`;
            });
        });
    };

    const fetchData = async () => {
        try {
            const result = await getMyListings();
            if (result.success) {
                const listings = result.data;
                
                const categorized = {
                    lostPending: [],
                    lostSolved: [],
                    foundUnclaimed: [],
                    foundClaimed: [],
                };

                listings.forEach((item) => {
                    if (item.listing_type === "lost") {
                        if (item.status === "pending") {
                            categorized.lostPending.push(item);
                        } else {
                            categorized.lostSolved.push(item);
                        }
                    } else if (item.listing_type === "found") {
                        if (item.status === "unclaimed") {
                            categorized.foundUnclaimed.push(item);
                        } else {
                            categorized.foundClaimed.push(item);
                        }
                    }
                });

                // Helper to render lists by appending nodes
                const renderList = (element, data) => {
                    element.innerHTML = ""; // Clear previous content
                    if (data.length > 0) {
                        data.forEach(item => {
                            element.appendChild(renderCard(item));
                        });
                    } else {
                        element.innerHTML = "<p>暂无信息</p>";
                    }
                };

                // Render listings
                renderList(lostPendingList, categorized.lostPending);
                renderList(lostSolvedList, categorized.lostSolved);
                renderList(foundUnclaimedList, categorized.foundUnclaimed);
                renderList(foundClaimedList, categorized.foundClaimed);
                // 获取并渲染匹配度较高的物品
                fetchMatchedListings();
                // 获取并渲染我提交的认领申请记录
                fetchMyClaims();

                // Add event listeners for delete buttons
                document.querySelectorAll(".btn-delete").forEach((button) => {
                    button.addEventListener("click", handleDelete);
                });
                addCardClickListeners();
            } else {
                console.error("获取列表失败:", result.message);
                lostPendingList.innerHTML = `<p class="message error">${result.message}</p>`;
            }
        } catch (error) {
            console.error("获取我的列表时出错:", error);
            lostPendingList.innerHTML = `<p class="message error">加载失败，请重试。</p>`;
        }
    };

    const handleDelete = async (e) => {
        const id = e.target.dataset.id;
        const card = e.target.closest('.listing-card');
        const type = card ? card.dataset.type : undefined;
        if (!confirm("您确定要删除此物品吗？此操作不可撤销。")) {
            return;
        }

        try {
            const result = await deleteListing(id, type);
            if (result.success) {
                const card = document.querySelector(`.card[data-id='${id}']`);
                if (card) {
                    // 添加动画class
                    card.classList.add('removing');
                    
                    // 等待动画结束后再移除DOM元素
                    card.addEventListener('transitionend', () => {
                        card.remove();
                        
                        // 检查是否删除了最后一个物品，更新UI显示
                        const container = card.parentElement;
                        if (container && container.children.length === 0) {
                            container.innerHTML = "<p>暂无信息</p>";
                        }
                    });
                }
                // 显示非阻塞式成功提示
                const messageBox = document.createElement('div');
                messageBox.className = 'message success';
                messageBox.style.position = 'fixed';
                messageBox.style.top = '20px';
                messageBox.style.left = '50%';
                messageBox.style.transform = 'translateX(-50%)';
                messageBox.style.padding = '10px 20px';
                messageBox.style.background = '#4CAF50';
                messageBox.style.color = 'white';
                messageBox.style.borderRadius = '5px';
                messageBox.style.zIndex = '1000';
                messageBox.textContent = '删除成功！';
                document.body.appendChild(messageBox);
                
                // 2秒后自动移除提示
                setTimeout(() => {
                    messageBox.style.opacity = '0';
                    messageBox.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => messageBox.remove(), 500);
                }, 2000);
            } else {
                alert(`删除失败: ${result.message}`);
            }
        } catch (error) {
            console.error("删除失败:", error);
            alert(`删除失败: ${error.message || "发生未知错误"}`);
        }
    };

    // 获取并渲染匹配度较高的物品
    const fetchMatchedListings = async () => {
        try {
            const result = await getMatchedListings();
            if (result.success) {
                const data = result.data;
                matchedList.innerHTML = "";
                if (data.length > 0) {
                    data.forEach(item => {
                        matchedList.appendChild(renderCard(item, true));
                    });
                } else {
                    matchedList.innerHTML = "<p>暂无匹配物品</p>";
                }
                addCardClickListeners();
            } else {
                matchedList.innerHTML = `<p class="message error">${result.message}</p>`;
            }
        } catch (error) {
            matchedList.innerHTML = `<p class="message error">加载失败，请重试。</p>`;
        }
    };

    // 获取并渲染我作为失主提交的认领申请记录（认领记录）
    const fetchMyClaims = async () => {
        if (!myClaimsList) return;
        try {
            const res = await getMyClaims();
            myClaimsList.innerHTML = '';
            if (!res || !res.success) {
                myClaimsList.innerHTML = `<p class="message error">${res ? res.message : '加载失败'}</p>`;
                return;
            }
            const items = (res.data && res.data.items) || [];
            if (items.length === 0) {
                myClaimsList.innerHTML = '<p>暂无认领申请记录</p>';
                return;
            }
            items.forEach(c => {
                const card = document.createElement('div');
                card.className = 'card listing-card';
                const imgUrl = c.found_image ? `../backend/${c.found_image}` : 'images/default.png';
                const statusBadge = c.status === 'processing'
                    ? '<span class="status-badge processing">处理中（待审核）</span>'
                    : '<span class="status-badge completed">已完成</span>';
                card.innerHTML = `
                    <img src="${imgUrl}" alt="${c.found_title || '招领物品'}" class="card-image">
                    <div class="card-content">
                        <h3 class="card-title">招领：${c.found_title || '未命名'}</h3>
                        <p class="card-date">申请时间：${new Date(c.created_at).toLocaleString()}</p>
                        <p><strong>招领发布者：</strong>${c.found_username || '-'}</p>
                        <p><strong>对应失物：</strong>${c.lost_title || '-'}</p>
                        <p><strong>状态：</strong>${statusBadge}</p>
                        <p style="font-size:0.92em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <strong>物品特征：</strong>${c.claim_features ? (c.claim_features.length > 40 ? c.claim_features.slice(0, 40) + '...' : c.claim_features) : '-'}</p>
                        <div class="card-actions">
                            <a href="details.html?id=${c.found_listing_id}&type=found" class="action-btn btn-view">查看招领详情</a>
                            <a href="details.html?id=${c.lost_listing_id}&type=lost" class="action-btn btn-edit" style="margin-left:8px;">查看对应失物</a>
                        </div>
                    </div>`;
                myClaimsList.appendChild(card);
            });
        } catch (error) {
            console.error('fetchMyClaims err:', error);
            myClaimsList.innerHTML = `<p class="message error">加载认领申请记录失败：${error.message || '未知错误'}</p>`;
        }
    };

    // 检查是否有新匹配物品
    function checkMatchedNotification() {
        getMatchedListings().then(result => {
            if (result.success && result.data && result.data.length > 0) {
                matchNotification.style.display = "block";
            } else {
                matchNotification.style.display = "none";
            }
        }).catch(() => {
            matchNotification.style.display = "none";
        });
    }
    if (matchNotification) {
        matchNotification.addEventListener("click", () => {
            window.location.href = "profile.html";
        });
    }
    checkMatchedNotification();

    fetchData();
}); 