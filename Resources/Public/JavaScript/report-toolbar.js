import DocumentService from '@typo3/core/document-service.js';
import Notification from '@typo3/backend/notification.js';

/**
 * Wires up the "Copy to clipboard" button inside the nr_bug_reporter toolbar dropdown. Uses event
 * delegation so it keeps working as the toolbar dropdown is (re)rendered. The GitHub report links
 * are plain anchors and need no JavaScript.
 */
class ReportToolbar {
  constructor() {
    DocumentService.ready().then(() => this.initialize());
  }

  initialize() {
    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-nr-bug-reporter-copy]');
      if (button === null) {
        return;
      }
      event.preventDefault();

      const scope = button.closest('.dropdown-list, .toolbar-item, li') ?? document;
      const textarea = scope.querySelector('[data-nr-bug-reporter-report]');
      if (textarea === null) {
        return;
      }

      this.copy(textarea.value, textarea);
    });
  }

  copy(text, textarea) {
    const onSuccess = () => Notification.success('Copied', 'Report copied to clipboard.');
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      navigator.clipboard.writeText(text).then(onSuccess, () => this.fallback(textarea, onSuccess));
      return;
    }
    this.fallback(textarea, onSuccess);
  }

  fallback(textarea, onSuccess) {
    textarea.removeAttribute('readonly');
    textarea.select();
    document.execCommand('copy');
    textarea.setAttribute('readonly', 'readonly');
    onSuccess();
  }
}

export default new ReportToolbar();
