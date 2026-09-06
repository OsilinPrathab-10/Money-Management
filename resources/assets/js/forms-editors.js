/**
 * Form Editors
 */

'use strict';

(function () {
  // Snow Theme
  // --------------------------------------------------------------------
  const snowEditorEl = document.querySelector('#snow-editor');
  if (snowEditorEl) {
    const snowEditor = new Quill('#snow-editor', {
      bounds: '#snow-editor',
      modules: {
        syntax: true,
        toolbar: '#snow-toolbar'
      },
      theme: 'snow'
    });
  }

  // Bubble Theme
  // --------------------------------------------------------------------
  const bubbleEditorEl = document.querySelector('#bubble-editor');
  if (bubbleEditorEl) {
    const bubbleEditor = new Quill('#bubble-editor', {
      modules: {
        toolbar: '#bubble-toolbar'
      },
      theme: 'bubble'
    });
  }

  // Full Toolbar
  // --------------------------------------------------------------------
  const fullToolbar = [
    [
      {
        font: []
      },
      {
        size: []
      }
    ],
    ['bold', 'italic', 'underline', 'strike'],
    [
      {
        color: []
      },
      {
        background: []
      }
    ],
    [
      {
        script: 'super'
      },
      {
        script: 'sub'
      }
    ],
    [
      {
        header: '1'
      },
      {
        header: '2'
      },
      'blockquote',
      'code-block'
    ],
    [
      {
        list: 'ordered'
      },
      {
        indent: '-1'
      },
      {
        indent: '+1'
      }
    ],
    [{ direction: 'rtl' }, { align: [] }],
    ['link', 'image', 'video', 'formula'],
    ['clean']
  ];
  
  const fullEditorEl = document.querySelector('#full-editor');
  if (fullEditorEl) {
    const fullEditor = new Quill('#full-editor', {
      bounds: '#full-editor',
      placeholder: 'Type Something...',
      modules: {
        syntax: true,
        toolbar: fullToolbar
      },
      theme: 'snow'
    });
  }
})();
