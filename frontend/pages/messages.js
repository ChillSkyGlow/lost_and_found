import { getMessages } from '../api/index.js';
import { checkSessionAndSetupHeader } from './auth.js';
import { $ } from '../utils/dom.js';

// 添加标记消息为已读的函数
async function markMessageAsRead(messageId, messageType, listingType = null) {
    try {
        const formData = new FormData();
        formData.append('message_id', messageId);
        formData.append('message_type', messageType);
        if (messageType === 'comment' && listingType) {
            formData.append('listing_type', listingType);
        }
        
        await fetch('../backend/api/users/mark_message_read.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
    } catch (error) {
        console.error('标记消息已读失败:', error);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    await checkSessionAndSetupHeader();
    const commentMessagesList = $('#comment-messages-list');
    const matchMessagesList = $('#match-messages-list');
    const claimMessagesList = $('#claim-messages-list');
    const claimReviewMessagesList = $('#claim-review-messages-list');
    
    try {
        commentMessagesList.innerHTML = '<p>正在加载评论消息...</p>';
        matchMessagesList.innerHTML = '<p>正在加载匹配消息...</p>';
        if (claimMessagesList) claimMessagesList.innerHTML = '<p>正在加载认领申请消息...</p>';
        if (claimReviewMessagesList) claimReviewMessagesList.innerHTML = '<p>正在加载认领审核结果消息...</p>';
        
        const result = await getMessages();
        
        console.log('API响应:', result);
        
        const commentMessages = [];
        const matchMessages = [];
        const claimMessages = [];
        const claimReviewMessages = [];
        
        if (result.success && result.data && result.data.length > 0) {
            result.data.forEach(msg => {
                if (msg.type === 'match') {
                    matchMessages.push(msg);
                } else if (msg.type === 'comment') {
                    commentMessages.push(msg);
                } else if (msg.type === 'claim') {
                    claimMessages.push(msg);
                } else if (msg.type === 'claim_approved' || msg.type === 'claim_rejected') {
                    claimReviewMessages.push(msg);
                }
            });
        }
        
        // 渲染评论消息
        if (commentMessages.length > 0) {
            commentMessagesList.innerHTML = commentMessages.map(msg => {
                const itemName = msg.item_name ? `"${msg.item_name}"` : '您的帖子';
                return `<div class="message-item comment-message" data-id="${msg.listing_id}" data-type="${msg.listing_type}" data-message-id="${msg.id}" data-message-type="comment">
                    ${itemName}有新的留言！<span class="message-time">${msg.time || ''}</span>
                </div>`;
            }).join('');
            
            // 添加点击事件
            commentMessagesList.querySelectorAll('.message-item').forEach(item => {
                item.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const type = this.getAttribute('data-type');
                    const messageId = this.getAttribute('data-message-id');
                    const messageType = this.getAttribute('data-message-type');
                    
                    // 标记消息为已读
                    markMessageAsRead(messageId, messageType, type);
                    
                    // 跳转到详情页
                    window.location.href = `details.html?id=${id}&type=${type}`;
                });
            });
        } else {
            commentMessagesList.innerHTML = '<p class="no-messages">暂无评论消息</p>';
        }
        
        // 渲染匹配消息
        if (matchMessages.length > 0) {
            matchMessagesList.innerHTML = matchMessages.map(msg => {
                const sourceItemName = msg.source_item_name ? `"${msg.source_item_name}"` : '您发布的物品';
                const matchedItemName = msg.item_name ? `"${msg.item_name}"` : '一件物品';
                
                return `<div class="message-item match-message" data-id="${msg.listing_id}" data-type="${msg.listing_type}" data-message-id="${msg.id}" data-message-type="match">
                    ${sourceItemName} 与 ${matchedItemName} 匹配！<span class="message-time">${msg.time || ''}</span>
                </div>`;
            }).join('');
            
            // 添加点击事件
            matchMessagesList.querySelectorAll('.message-item').forEach(item => {
                item.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const type = this.getAttribute('data-type');
                    const messageId = this.getAttribute('data-message-id');
                    const messageType = this.getAttribute('data-message-type');
                    
                    // 标记消息为已读
                    markMessageAsRead(messageId, messageType, type);
                    
                    // 跳转到详情页
                    window.location.href = `details.html?id=${id}&type=${type}`;
                });
            });
        } else {
            matchMessagesList.innerHTML = '<p class="no-messages">暂无匹配消息</p>';
        }

        if (claimMessagesList) {
            if (claimMessages.length > 0) {
                claimMessagesList.innerHTML = claimMessages.map(msg => {
                    const sourceItemName = msg.source_item_name ? `"${msg.source_item_name}"` : '失主的物品';
                    const myItemName = msg.item_name ? `"${msg.item_name}"` : '您发布的招领';
                    return `<div class="message-item claim-message" data-id="${msg.listing_id}" data-type="${msg.listing_type}" data-message-id="${msg.id}" data-message-type="claim">
                        失主${sourceItemName}的发布者对您发布的招领${myItemName}提交了认领申请，点击查看详情<span class="message-time">${msg.time || ''}</span>
                    </div>`;
                }).join('');
                claimMessagesList.querySelectorAll('.message-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const type = this.getAttribute('data-type');
                        const messageId = this.getAttribute('data-message-id');
                        const messageType = this.getAttribute('data-message-type');
                        markMessageAsRead(messageId, messageType, type);
                        window.location.href = `details.html?id=${id}&type=${type}`;
                    });
                });
            } else {
                claimMessagesList.innerHTML = '<p class="no-messages">暂无认领申请消息</p>';
            }
        }

        if (claimReviewMessagesList) {
            if (claimReviewMessages.length > 0) {
                claimReviewMessagesList.innerHTML = claimReviewMessages.map(msg => {
                    const myItemName = msg.item_name ? `"${msg.item_name}"` : '招领物品';
                    const tip = msg.type === 'claim_approved'
                        ? `恭喜：您对招领${myItemName}的认领申请已通过审核！请及时联系招领发布者领取物品。`
                        : `您对招领${myItemName}的认领申请未通过审核，可重新发布失物信息后再尝试。`;
                    return `<div class="message-item ${msg.type}-message" data-id="${msg.listing_id}" data-type="${msg.listing_type}" data-message-id="${msg.id}" data-message-type="${msg.type}">
                        ${tip}<span class="message-time">${msg.time || ''}</span>
                    </div>`;
                }).join('');
                claimReviewMessagesList.querySelectorAll('.message-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const type = this.getAttribute('data-type');
                        const messageId = this.getAttribute('data-message-id');
                        const messageType = this.getAttribute('data-message-type');
                        markMessageAsRead(messageId, messageType, type);
                        window.location.href = `details.html?id=${id}&type=${type}`;
                    });
                });
            } else {
                claimReviewMessagesList.innerHTML = '<p class="no-messages">暂无认领审核结果消息</p>';
            }
        }
        
    } catch (e) {
        console.error('消息加载错误:', e);
        commentMessagesList.innerHTML = `<p style="color:#d9534f;">加载评论消息失败: ${e.message}</p>`;
        matchMessagesList.innerHTML = `<p style="color:#d9534f;">加载匹配消息失败: ${e.message}</p>`;
        if (claimMessagesList) claimMessagesList.innerHTML = `<p style="color:#d9534f;">加载认领申请消息失败: ${e.message}</p>`;
    }
});