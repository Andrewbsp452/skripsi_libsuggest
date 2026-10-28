'use strict';

(() => {
  const backdrop = document.getElementById('chat-modal-backdrop');
  const messagesElement = document.getElementById('chat-messages');
  const form = document.getElementById('chat-message-form');
  const input = document.getElementById('chat-message-input');
  const sendButton = document.getElementById('chat-send-button');
  const inbox = document.getElementById('admin-chat-inbox');
  const requesterList = document.getElementById('requester-chat-list');
  if (!backdrop || !messagesElement || !form || !input || !sendButton) return;

  const currentRole = window.LIBSUGGEST_CHAT_ROLE || 'pengusul';
  let activeProposalId = null;
  let refreshTimer = null;
  let isLoading = false;

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[character]);
  }

  async function request(url, options = {}) {
    const headers = { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) };
    if (options.method === 'POST') {
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]')?.content || '';
    }
    const response = await fetch(url, { ...options, headers });
    const data = await response.json();
    if (!response.ok || data.error) throw new Error(data.error || 'Permintaan gagal.');
    return data;
  }

  function close() {
    backdrop.classList.remove('open');
    activeProposalId = null;
    window.clearInterval(refreshTimer);
  }

  async function loadMessages() {
    if (!activeProposalId || isLoading) return;
    const proposalId = activeProposalId;
    isLoading = true;
    try {
      const data = await request(`api/chat.php?action=messages&proposal_id=${proposalId}`);
      if (activeProposalId !== proposalId) return;
      const hasUnread = data.messages.some(message => message.sender_role !== currentRole && !message.read_at);
      if (hasUnread) {
        await request('api/chat.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'mark_read', proposal_id: proposalId }),
        });
        if (activeProposalId !== proposalId) return;
        if (inbox) loadInbox();
      }
      document.getElementById('chat-modal-title').textContent = data.proposal.title;
      document.getElementById('chat-modal-subtitle').textContent = 'Diskusi dengan admin perpustakaan';
      messagesElement.innerHTML = data.messages.length
        ? data.messages.map(message => {
          const ownMessage = message.sender_role === currentRole;
          const date = new Date(message.created_at).toLocaleString('id-ID', {
            day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
          });
          return `<article class="chat-message ${ownMessage ? 'own' : ''}">
            <div class="chat-message-meta">${escapeHtml(message.sender_name)} · ${date}</div>
            <div class="chat-message-body">${escapeHtml(message.message).replace(/\n/g, '<br>')}</div>
          </article>`;
        }).join('')
        : '<p class="chat-empty">Belum ada pesan. Sampaikan alasan kebutuhan buku ini kepada admin.</p>';
      messagesElement.scrollTop = messagesElement.scrollHeight;
    } catch (error) {
      if (activeProposalId !== proposalId) return;
      messagesElement.innerHTML = `<p class="chat-error">${escapeHtml(error.message)}</p>`;
      window.clearInterval(refreshTimer);
    } finally {
      isLoading = false;
    }
  }

  async function open(proposalId) {
    activeProposalId = Number(proposalId);
    if (!Number.isInteger(activeProposalId) || activeProposalId < 1) return;
    const openedProposalId = activeProposalId;
    backdrop.classList.add('open');
    messagesElement.innerHTML = '<p class="chat-empty">Memuat percakapan...</p>';
    input.value = '';
    try {
      await request('api/chat.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'mark_read', proposal_id: openedProposalId }),
      });
      if (activeProposalId !== openedProposalId) return;
      await loadMessages();
      window.clearInterval(refreshTimer);
      refreshTimer = window.setInterval(loadMessages, 5000);
      input.focus();
      if (inbox) loadInbox();
    } catch (error) {
      messagesElement.innerHTML = `<p class="chat-error">${escapeHtml(error.message)}</p>`;
    }
  }

  async function loadInbox() {
    if (!inbox) return;
    try {
      const data = await request('api/chat.php?action=inbox');
      if (!data.conversations.length) {
        inbox.innerHTML = '<p class="chat-empty">Belum ada percakapan keberatan.</p>';
        return;
      }
      inbox.innerHTML = data.conversations.map(conversation => `
        <article class="chat-inbox-item">
          <div class="chat-inbox-copy">
            <strong>${escapeHtml(conversation.title)}</strong>
            <span>${escapeHtml(conversation.user_name)} (${escapeHtml(conversation.user_role)})</span>
            <span class="chat-inbox-preview">${escapeHtml(conversation.last_message)}</span>
          </div>
          <div class="chat-inbox-action">
            ${Number(conversation.unread_count) > 0 ? `<span class="chat-unread">${Number(conversation.unread_count)} baru</span>` : ''}
            <button type="button" class="btn btn-outline btn-chat-open" data-proposal-id="${Number(conversation.proposal_id)}">Buka chat</button>
          </div>
        </article>`).join('');
    } catch (error) {
      inbox.innerHTML = `<p class="chat-error">${escapeHtml(error.message)}</p>`;
    }
  }

  async function loadRequesterProposals() {
    if (!requesterList) return;
    try {
      const data = await request('api/chat.php?action=my_rejected');
      if (!data.proposals.length) {
        requesterList.innerHTML = '<p class="chat-empty">Belum ada usulan buku yang ditolak.</p>';
        return;
      }
      requesterList.innerHTML = data.proposals.map(proposal => `
        <article class="chat-inbox-item">
          <div class="chat-inbox-copy">
            <strong>${escapeHtml(proposal.title)}</strong>
            <span>Ditolak${proposal.kategori_verifikasi ? ` · ${escapeHtml(proposal.kategori_verifikasi)}` : ''}</span>
            ${proposal.note_petugas ? `<span class="chat-inbox-preview">${escapeHtml(proposal.note_petugas)}</span>` : ''}
          </div>
          <div class="chat-inbox-action">
            ${Number(proposal.unread_count) > 0 ? `<span class="chat-unread">${Number(proposal.unread_count)} balasan baru</span>` : ''}
            <button type="button" class="btn btn-outline btn-chat-open" data-proposal-id="${Number(proposal.proposal_id)}">
              ${Number(proposal.message_count) > 0 ? 'Lanjutkan percakapan' : 'Mulai percakapan'}
            </button>
          </div>
        </article>`).join('');
    } catch (error) {
      requesterList.innerHTML = `<p class="chat-error">${escapeHtml(error.message)}</p>`;
    }
  }

  document.addEventListener('click', event => {
    const button = event.target.closest('.btn-chat-open');
    if (button) open(button.dataset.proposalId);
  });

  document.getElementById('chat-modal-close')?.addEventListener('click', close);
  backdrop.addEventListener('click', event => {
    if (event.target === backdrop) close();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') close();
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const message = input.value.trim();
    if (!activeProposalId || !message) return;
    const proposalId = activeProposalId;
    sendButton.disabled = true;
    try {
      await request('api/chat.php', {
        method: 'POST',
        body: JSON.stringify({
          action: 'send',
          proposal_id: proposalId,
          message,
        }),
      });
      if (activeProposalId === proposalId) {
        input.value = '';
        await loadMessages();
      }
      if (inbox) loadInbox();
    } catch (error) {
      Toast.error(error.message);
    } finally {
      sendButton.disabled = false;
      input.focus();
    }
  });

  document.getElementById('chat-inbox-refresh')?.addEventListener('click', loadInbox);
  document.getElementById('requester-chat-refresh')?.addEventListener('click', loadRequesterProposals);
  if (inbox) {
    loadInbox();
    window.setInterval(loadInbox, 30000);
  }
  if (requesterList) {
    loadRequesterProposals();
    window.setInterval(loadRequesterProposals, 30000);
  }
})();
