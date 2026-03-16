// This file will contain the logic for the details page.

import {
    getListingDetails,
    postComment,
    deleteListing,
    updateListingStatus,
} from "../api/index.js";
import { checkSessionAndSetupHeader } from "./auth.js";
import { initDisplayMap } from "../utils/map.js";
import { showMessage, hideMessage } from "../utils/dom.js";
import { sanitizeHTML } from "../utils/sanitize.js";

document.addEventListener("DOMContentLoaded", async () => {
    const session = await checkSessionAndSetupHeader();
    // 注意：不需要重定向，允许未登录用户查看物品详情

    const detailsContainer = document.getElementById("details-container");
    const commentsList = document.getElementById("comments-list");
    const commentForm = document.getElementById("comment-form");
    const messageBox = document.getElementById("comment-message-box");

    const urlParams = new URLSearchParams(window.location.search);
    const listingId = urlParams.get("id");
    const listingType = urlParams.get("type"); // 获取 type

    if (!listingId || !listingType) { // 确保 id 和 type 都存在
        detailsContainer.innerHTML =
            '<p class="message error">错误：链接无效，缺少物品ID或类型。</p>';
        return;
    }

    const fetchData = async () => {
        try {
            // 将 id 和 type 都传递给 API
            const result = await getListingDetails(listingId, listingType);
            if (result.success) {
                renderDetails(result.data.details, session.userId);
                renderComments(result.data.comments);
            } else {
                detailsContainer.innerHTML = `<p class="message error">${sanitizeHTML(
                    result.message
                )}</p>`;
            }
        } catch (error) {
            console.error("获取详情失败:", error);
            detailsContainer.innerHTML =
                '<p class="message error">加载失败，请检查网络并重试。</p>';
        }
    };

    const renderDetails = (details, currentUserId) => {
        if (!details) {
            detailsContainer.innerHTML =
                '<p class="message error">未找到该物品的详情。</p>';
            return;
        }

        const isOwner = details.user_id === currentUserId;
        const isPending = details.status === 'pending' || details.status === 'unclaimed';
        
        // 根据类型和状态确定显示的文本
        let statusText = '';
        if (details.listing_type === 'lost') {
            statusText = details.status === 'pending' ? '进行中 (等待找回)' : '已完成 (已找回)';
        } else if (details.listing_type === 'found') {
            statusText = details.status === 'unclaimed' ? '进行中 (等待认领)' : '已完成 (已认领)';
        }

        const detailsHTML = `
            <div class="details-grid">
                <div class="details-image">
                    ${
                        details.image_path
                            ? `<img src="../backend/${sanitizeHTML(
                                  details.image_path
                              )}" alt="${sanitizeHTML(details.title)}">`
                            : '<img src="images/default.png" alt="默认图片">'
                    }
                </div>
                <div class="details-info">
                    <h2>${sanitizeHTML(details.title)}</h2>
                    <div class="meta-info">
                        <span><strong>类型:</strong> ${
                            details.listing_type === "lost" ? "寻物" : "招领"
                        }</span>
                        <span><strong>状态:</strong> ${statusText}</span>
                        <span><strong>发布者:</strong> ${sanitizeHTML(details.username)}</span>
                        <span><strong>发布时间:</strong> ${new Date(
                            details.created_at
                        ).toLocaleString()}</span>
                    </div>
                    <p class="item-description"><strong>描述:</strong> ${sanitizeHTML(details.description)}</p>
                    ${
                        isOwner && isPending ? `
                    <div class="item-actions">
                        <a href="edit_listing.html?id=${details.id}&type=${details.listing_type}" class="action-btn btn-edit">编辑</a>
                        <button id="delete-btn" class="action-btn btn-delete" data-id="${details.id}">删除</button>
                    </div>` : ""
                    }
                    ${ isOwner && isPending ? `
                    <div class="solve-action-container">
                        <button id="solve-btn" class="action-btn btn-solve" data-id="${details.id}" data-type="${details.listing_type}">
                            ${details.listing_type === 'lost' ? '我已找回' : '我已归还'}
                        </button>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;

        detailsContainer.innerHTML = detailsHTML;
        
        if (details.location_coords) {
            initDisplayMap("details-map-container", details.location_coords);
        }

        if (isOwner) {
            const deleteBtn = document.getElementById("delete-btn");
            if (deleteBtn) {
                deleteBtn.addEventListener("click", handleDelete);
            }
            const solveBtn = document.getElementById("solve-btn");
            if(solveBtn) {
                solveBtn.addEventListener("click", handleSolve);
            }
        }
    };

    const renderComments = (comments) => {
        if (comments && comments.length > 0) {
            commentsList.innerHTML = comments
                .map((comment) => createCommentHTML(comment))
                .join("");
        } else {
            commentsList.innerHTML = "<p>暂无评论。</p>";
        }
    };

    const createCommentHTML = (comment) => {
        return `
            <div class="comment-item">
                <p><strong>${sanitizeHTML(
                    comment.username
                )}</strong> <span class="comment-date">(${new Date(
            comment.created_at
        ).toLocaleString()})</span>:</p>
                <p>${sanitizeHTML(comment.content)}</p>
            </div>
        `;
    };

    const appendComment = (comment) => {
        const noCommentsEl = commentsList.querySelector("p");
        if (noCommentsEl && noCommentsEl.textContent === "暂无评论。") {
            commentsList.innerHTML = "";
        }
        commentsList.insertAdjacentHTML("beforeend", createCommentHTML(comment));
    };

    const handleCommentSubmit = async (e) => {
        e.preventDefault();
        hideMessage(messageBox);

        const content = e.target.content.value.trim();
        if (!content) {
            showMessage(messageBox, "error", "评论内容不能为空。");
            return;
        }

        try {
            const result = await postComment(listingId, content, listingType);
            if (result.success) {
                showMessage(messageBox, "success", "评论成功！");
                appendComment({
                    ...result.data,
                    username: session.username, // 使用当前登录用户的信息
                });
                e.target.reset(); // 清空表单
            } else {
                showMessage(messageBox, "error", result.message);
            }
        } catch (error) {
            console.error("评论失败:", error);
            showMessage(messageBox, "error", "评论时发生网络错误，请重试。");
        }
    };

    const handleSolve = async (e) => {
        const button = e.target;
        const id = button.dataset.id;
        const type = button.dataset.type;

        if (!confirm(`您确定要将此物品标记为已解决吗？`)) {
            return;
        }
        
        button.disabled = true;
        button.textContent = '处理中...';

        try {
            // 创建FormData对象，确保参数正确传递
            const formData = new FormData();
            formData.append('listing_id', id);
            formData.append('type', type);
            
            // 直接使用fetch API调用，避免封装的API可能存在的问题
            const response = await fetch('../backend/api/listings/update_listing_status.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            
            // 获取响应文本
            const responseText = await response.text();
            let result;
            
            try {
                // 尝试解析JSON
                result = JSON.parse(responseText);
            } catch (parseError) {
                // 如果无法解析JSON，显示原始响应
                console.error('无法解析服务器响应:', responseText);
                throw new Error(`服务器返回了无效的数据格式: ${responseText.substring(0, 100)}...`);
            }
            
            if (result.success) {
                // 状态更新成功后，直接刷新详情内容，彻底移除操作按钮
                fetchData();
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
                messageBox.textContent = '状态更新成功！';
                document.body.appendChild(messageBox);
                // 2秒后自动移除提示
                setTimeout(() => {
                    messageBox.style.opacity = '0';
                    messageBox.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => messageBox.remove(), 500);
                }, 2000);
                return;
            } else {
                alert(`操作失败: ${result.message}`);
                button.disabled = false;
                button.textContent = type === 'lost' ? '我已找回' : '我已归还';
            }
        } catch (error) {
            console.error('状态更新失败:', error);
            alert(`更新失败: ${error.message || '网络错误，请稍后重试。'}`);
            button.disabled = false;
            button.textContent = type === 'lost' ? '我已找回' : '我已归还';
        }
    };

    const handleDelete = async (e) => {
        const id = e.target.dataset.id;
        if (!confirm("您确定要删除此物品吗？此操作不可撤销。")) {
            return;
        }

        try {
            const result = await deleteListing(id);
            if (result.success) {
                alert("删除成功！");
                window.location.href = "index.html";
            } else {
                alert(`删除失败: ${result.message}`);
            }
        } catch (error) {
            console.error("删除失败:", error);
            alert("删除时发生网络错误，请重试。");
        }
    };

    commentForm.addEventListener("submit", handleCommentSubmit);

    fetchData();
}); 