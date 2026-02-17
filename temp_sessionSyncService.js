const logger = require('../utils/logger');
const sessionManager = require('./sessionManager');

class SessionSyncService {
  constructor() {
    this.syncInterval = null;
    this.syncIntervalMs = 60000;
  }

  async syncSessions(correlationId) {
    try {
      logger.info('Starting session sync', { correlationId });
      const wppSessions = await this.getWPPConnectSessions(correlationId);
      const localSessions = sessionManager.getAllSessions();
      const localSessionIds = new Set(localSessions.map(s => s.session_id));

      let added = 0;
      for (const wppSession of wppSessions) {
        if (!localSessionIds.has(wppSession.id)) {
          sessionManager.addSession(wppSession.id, {
            name: wppSession.name || wppSession.id,
            status: wppSession.status || 'unknown'
          });
          added++;
          logger.info('Session auto-synced from WPPConnect', {
            sessionId: wppSession.id,
            status: wppSession.status
          });
        }
      }

      let updated = 0;
      for (const localSession of localSessions) {
        const wppSession = wppSessions.find(s => s.id === localSession.session_id);
        if (wppSession && wppSession.status !== localSession.status) {
          sessionManager.updateSessionStatus(localSession.session_id, wppSession.status);
          updated++;
        }
      }

      logger.info('Session sync completed', { total: wppSessions.length, added, updated, correlationId });
      return { success: true, total: wppSessions.length, added, updated };
    } catch (error) {
      logger.error('Error syncing sessions', { error: error.message, correlationId });
      return { success: false, error: error.message };
    }
  }

  async getWPPConnectSessions(correlationId) {
    try {
      const localSessions = sessionManager.getAllSessions();
      return localSessions.map(s => ({
        id: s.session_id,
        name: s.name || s.session_id,
        status: s.status || 'unknown'
      }));
    } catch (error) {
      logger.warn('Could not fetch sessions', { error: error.message, correlationId });
      return [];
    }
  }

  normalizeStatus(status) {
    if (!status) return 'unknown';
    const statusLower = String(status).toLowerCase();
    if (statusLower.includes('connected') || statusLower === 'open') return 'connected';
    if (statusLower.includes('disconnected') || statusLower === 'close' || statusLower === 'closed') return 'disconnected';
    if (statusLower.includes('qr') || statusLower === 'qr_required' || statusLower.includes('initializing') || statusLower.includes('starting')) return 'qr_required';
    return statusLower;
  }

  startAutoSync() {
    if (this.syncInterval) {
      logger.warn('Auto-sync already running');
      return;
    }
    logger.info('Starting auto-sync', { intervalMs: this.syncIntervalMs });
    this.syncSessions('auto-sync-initial');
    this.syncInterval = setInterval(() => {
      this.syncSessions('auto-sync-periodic');
    }, this.syncIntervalMs);
  }

  stopAutoSync() {
    if (this.syncInterval) {
      clearInterval(this.syncInterval);
      this.syncInterval = null;
      logger.info('Auto-sync stopped');
    }
  }
}

module.exports = new SessionSyncService();
