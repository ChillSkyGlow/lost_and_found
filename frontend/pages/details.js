// This file will contain the logic for the details page.

import {
    getListingDetails,
    postComment,
    deleteListing,
    updateListingStatus,
    submitClaim,
    reviewClaim,
    getClaimsForMyFound,
    getMyListings,
} from "../api/index.js";
import { checkSessionAndSetupHeader } from "./auth.js";
import { initDisplayMap } from "../utils/map.js";
import { showMessage, hideMessage } from "../utils/dom.js";
import { sanitizeHTML } from "../utils/sanitize.js";

document.addEventListener("DOMContentLoaded", async () => {
    const session = await checkSessionAndSetupHeader();

    const detailsContainer = document.getElementById("details-container");
    const commentsList = document.getElementById("comments-list");
    const commentForm = document.getElementById("comment-form");
    const messageBox = document.getElementById("comment-message-box");

    const claimSection = document.getElementById("claim-section");
    const claimSectionTitle = document.getElementById("claim-section-title");
    const claimMessageBox = document.getElementById("claim-message-box");
    const claimContentArea = document.getElementById("claim-content-area");

    const urlParams = new URLSearchParams(window.location.search);
    const listingId = urlParams.get("id");
    const listingType = urlParams.get("type");

    if (!listingId || !listingType) {
        detailsContainer.innerHTML =
            '<p class="message error">错误：链接无效，缺少物品ID或类型。</p>';
        return;
    }

    let currentDetails = null;

    const fetchData = async () => {
        try {
            const result = await getListingDetails(listingId, listingType);
            if (result.success) {
                currentDetails = result.data.details;
                renderDetails(result.data.details, session.userId);
                renderComments(result.data.comments);
                if (listingType === 'found') {
                    try {
                        await renderClaimSection(result.data.details, session);
                    } catch (e) {
                        console.error('renderClaimSection failed:', e);
                    }
                } else if (claimSection) {
                    claimSection.style.display = 'none';
                }
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

    const renderClaimSection = async (details, sess) => {
        if (!claimSection) return;
        hideMessage(claimMessageBox);
        claimSection.style.display = 'block';
        const isOwner = Number(details.user_id) === Number(sess.userId);
        const isUnclaimed = details.status === 'unclaimed';

        if (isOwner) {
            claimSectionTitle.textContent = '收到的认领申请';
            await renderIncomingClaimsList(details.id);
            return;
        }

        if (!sess.userId) {
            claimSectionTitle.textContent = '认领该物品';
            claimContentArea.innerHTML =
                '<p class="message info">您需要先登录才能提交认领申请。<a href="login.html">去登录</a></p>';
            return;
        }

        if (!isUnclaimed) {
            claimSectionTitle.textContent = '认领申请';
            claimContentArea.innerHTML =
                '<p class="message info">该招领信息已被认领或已解决，不再接受新申请。</p>';
            return;
        }

        claimSectionTitle.textContent = '提交认领申请';
        await renderClaimForm(details.id, sess.userId);
    };

    const renderClaimForm = async (foundListingId, currentUserId) => {
        try {
            let myLostItems = [];
            try {
                const mlRes = await getMyListings();
                if (mlRes && mlRes.success && Array.isArray(mlRes.data)) {
                    myLostItems = mlRes.data.filter(it =>
                        String(it.listing_type).toLowerCase() === 'lost' &&
                        (it.status === 'pending' || it.status === undefined || it.status === null)
                    );
                }
            } catch (e) {
                console.warn('getMyListings 失败，使用空列表', e);
            }

            let existingClaim = null;
            try {
                const mcRes = await fetch('/backend/api/listings/get_my_claims.php', {
                    method: 'GET', credentials: 'include'
                });
                if (mcRes.ok) {
                    const json = await mcRes.json();
                    if (json.success && json.data && json.data.items) {
                        existingClaim = json.data.items.find(
                            it => Number(it.found_listing_id) === Number(foundListingId)
                        );
                    }
                }
            } catch (e) {
                console.warn('查询我对该招领的申请历史失败', e);
            }

            if (existingClaim) {
                let st, stTip = '';
                if (existingClaim.status === 'processing') {
                    st = '审核中';
                } else if (existingClaim.status === 'completed') {
                    st = '认领成功';
                } else {
                    st = '审核未通过';
                    stTip = '<p class="message error" style="margin-top:8px;">招领发布者未能通过您的认领申请，可重新发布失物信息后再次尝试。</p>';
                }
                claimContentArea.innerHTML = `
                    <div class="claim-existing">
                        <p class="message info">您已对该招领提交过认领申请，当前状态：
                            <strong class="status-badge ${existingClaim.status}">${st}</strong>
                        </p>
                        ${stTip}
                        <p><strong>提交时间：</strong>${sanitizeHTML(new Date(existingClaim.created_at).toLocaleString())}</p>
                        ${existingClaim.solved_at ? `<p><strong>审核时间：</strong>${sanitizeHTML(new Date(existingClaim.solved_at).toLocaleString())}</p>` : ''}
                        <p><strong>对应失物：</strong>${sanitizeHTML(existingClaim.lost_title || '未指定')}</p>
                        <p><strong>物品特征：</strong>${sanitizeHTML(existingClaim.claim_features || '')}</p>
                    </div>`;
                return;
            }

            if (!myLostItems || myLostItems.length === 0) {
                claimContentArea.innerHTML = `
                    <p class="message warning">提交认领申请前，您需要先发布一条「失物信息」用于建立归属关联。
                        <a href="publish.html?type=lost">立即发布失物信息</a>
                    </p>`;
                return;
            }

            const options = myLostItems.map(it =>
                `<option value="${it.id}">${sanitizeHTML(it.title || it.item_name || `失物#${it.id}`)}</option>`
            ).join('');

            claimContentArea.innerHTML = `
                <form id="claim-form" class="claim-form">
                    <div class="form-group">
                        <label for="claim_lost_listing_id">选择您发布的失物记录（用于建立归属关联）<span class="req">*</span></label>
                        <select name="lost_listing_id" id="claim_lost_listing_id" required>
                            <option value="">-- 请选择您的失物 --</option>
                            ${options}
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="claim_features">物品特征（颜色、编号、特殊标识等）<span class="req">*</span></label>
                        <textarea id="claim_features" name="claim_features" rows="3" maxlength="500" required
                            placeholder="例：红色皮质卡套，内有校园卡，卡号尾号 1234，右侧有蓝色贴纸 500 字以内"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="claim_lost_story">丢失经过<span class="req">*</span></label>
                        <textarea id="claim_lost_story" name="lost_story" rows="4" required
                            placeholder="例：9 月 5 日中午 12:10 左右在第一食堂一楼打饭时丢失，当时点了番茄炒蛋..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="claim_verification">其他验证信息（选填，如可公开的证件号后 4 位、购买记录描述等）</label>
                        <textarea id="claim_verification" name="verification_info" rows="3"
                            placeholder="选填：卡号后四位 1234；2023 年 8 月在天猫 XX 旗舰店购入，订单号..."></textarea>
                    </div>
                    <div class="form-group actions">
                        <button type="submit" id="claim-submit-btn">提交认领申请</button>
                    </div>
                </form>`;

            const form = document.getElementById('claim-form');
            if (form) {
                form.addEventListener('submit', handleSubmitClaim);
            }
        } catch (err) {
            console.error('renderClaimForm 失败:', err);
            claimContentArea.innerHTML = `<p class="message error">加载认领表单失败：${sanitizeHTML(err.message || '未知错误')}</p>`;
        }
    };

    const handleSubmitClaim = async (e) => {
        e.preventDefault();
        hideMessage(claimMessageBox);
        const btn = document.getElementById('claim-submit-btn');
        try {
            if (btn) { btn.disabled = true; btn.textContent = '提交中...'; }
            const fd = new FormData(e.target);
            const payload = {
                found_listing_id: Number(listingId),
                lost_listing_id:   Number(fd.get('lost_listing_id') || 0),
                claim_features:    String(fd.get('claim_features') || '').trim(),
                lost_story:        String(fd.get('lost_story')     || '').trim(),
                verification_info: String(fd.get('verification_info') || '').trim() || null,
            };
            const res = await submitClaim(payload);
            if (res && res.success) {
                showMessage(claimMessageBox, 'success',
                    `认领申请已成功提交（编号 #${res.data.solve_id}），状态为处理中，等待招领发布者审核。`);
                e.target.reset();
                setTimeout(async () => { await renderClaimForm(Number(listingId), session.userId); }, 500);
            } else {
                showMessage(claimMessageBox, 'error',
                    (res && res.message) ? res.message : '认领申请提交失败，请稍后重试。');
            }
        } catch (err) {
            console.error('handleSubmitClaim err:', err);
            showMessage(claimMessageBox, 'error', err.message || '提交时发生网络错误，请稍后重试。');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = '提交认领申请'; }
        }
    };

    const handleReviewClaim = async (solveId, decision) => {
        const confirmMsg = decision === 'approve'
            ? '确定通过此认领申请？通过后招领物品将标记为已认领，失物标记为已解决。'
            : '确定拒绝此认领申请？拒绝后仅修改申请状态，不会改变招领和失物的状态。';
        if (!window.confirm(confirmMsg)) return;
        hideMessage(claimMessageBox);
        try {
            const res = await reviewClaim({ solve_id: solveId, decision });
            if (res && res.success) {
                showMessage(claimMessageBox, 'success',
                    decision === 'approve' ? '已通过该认领申请。' : '已拒绝该认领申请。');
                await renderIncomingClaimsList(Number(listingId));
            } else {
                showMessage(claimMessageBox, 'error',
                    (res && res.message) ? res.message : '审核操作失败，请稍后重试。');
            }
        } catch (err) {
            console.error('handleReviewClaim err:', err);
            showMessage(claimMessageBox, 'error', err.message || '审核时发生网络错误，请稍后重试。');
        }
    };

    const renderIncomingClaimsList = async (foundListingId) => {
        try {
            const res = await getClaimsForMyFound(foundListingId);
            if (!res || !res.success) {
                claimContentArea.innerHTML = `<p class="message error">${sanitizeHTML(res ? res.message : '查询失败')}</p>`;
                return;
            }
            const items = (res.data && res.data.items) || [];
            if (items.length === 0) {
                claimContentArea.innerHTML = '<p class="no-messages">暂无收到任何认领申请。</p>';
                return;
            }
            claimContentArea.innerHTML = `
                <p class="claim-count-info">当前共收到 <strong>${items.length}</strong> 份认领申请：</p>
                <div class="claim-list">
                    ${items.map(c => {
                        let statusBadge, actionBtns = '';
                        if (c.status === 'processing') {
                            statusBadge = '<span class="status-badge processing">处理中（待审核）</span>';
                            actionBtns = `
                            <div class="claim-card-actions">
                                <button type="button" class="btn-approve-claim" data-solve-id="${c.solve_id}">通过</button>
                                <button type="button" class="btn-reject-claim"  data-solve-id="${c.solve_id}" style="margin-left:10px;background:#d9534f;">拒绝</button>
                            </div>`;
                        } else if (c.status === 'completed') {
                            statusBadge = '<span class="status-badge completed">已通过（已认领）</span>';
                        } else {
                            statusBadge = '<span class="status-badge rejected">已拒绝</span>';
                        }
                        const lostImg = c.lost_image
                            ? `<img src="../backend/${sanitizeHTML(c.lost_image)}" alt="失物图片">`
                            : '<img src="images/default.png" alt="失物图片">';
                        return `
                        <div class="claim-card">
                            <div class="claim-card-head">
                                <div class="claim-card-img">${lostImg}</div>
                                <div class="claim-card-meta">
                                    <h4>失主：${sanitizeHTML(c.lost_username || '未知')}</h4>
                                    <p><strong>对应失物：</strong>${sanitizeHTML(c.lost_title || '未指定')}</p>
                                    <p><strong>提交时间：</strong>${sanitizeHTML(new Date(c.created_at).toLocaleString())}</p>
                                    ${c.solved_at ? `<p><strong>审核时间：</strong>${sanitizeHTML(new Date(c.solved_at).toLocaleString())}</p>` : ''}
                                    <p><strong>状态：</strong>${statusBadge}</p>
                                    <p style="font-size:0.9em;opacity:0.8;">联系方式（邮箱）：${sanitizeHTML(c.lost_email || '-')}</p>
                                </div>
                            </div>
                            <div class="claim-card-body">
                                <p><strong>物品特征：</strong></p>
                                <p class="claim-text">${sanitizeHTML(c.claim_features || '')}</p>
                                <p><strong>丢失经过：</strong></p>
                                <p class="claim-text">${sanitizeHTML(c.lost_story || '')}</p>
                                ${c.verification_info ? `
                                <p><strong>其他验证信息：</strong></p>
                                <p class="claim-text">${sanitizeHTML(c.verification_info)}</p>` : ''}
                                ${actionBtns}
                            </div>
                        </div>`;
                    }).join('')}
                </div>`;
            claimContentArea.querySelectorAll('.btn-approve-claim').forEach(btn => {
                btn.addEventListener('click', () => handleReviewClaim(Number(btn.dataset.solveId), 'approve'));
            });
            claimContentArea.querySelectorAll('.btn-reject-claim').forEach(btn => {
                btn.addEventListener('click', () => handleReviewClaim(Number(btn.dataset.solveId), 'reject'));
            });
        } catch (err) {
            console.error('renderIncomingClaimsList err:', err);
            claimContentArea.innerHTML = `<p class="message error">加载认领申请列表失败：${sanitizeHTML(err.message || '未知错误')}</p>`;
        }
    };

    commentForm.addEventListener("submit", handleCommentSubmit);

    fetchData();
}); 