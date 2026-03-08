import { marked } from 'marked';
import DOMPurify from 'dompurify';

marked.setOptions({
    breaks: true,
    gfm: true,
});

window.renderMarkdown = (text) => DOMPurify.sanitize(marked.parse(text));
