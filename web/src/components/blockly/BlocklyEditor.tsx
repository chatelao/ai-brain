'use client';

import React, { useState } from 'react';
import BlocklyComponent from './BlocklyComponent';

interface BlocklyEditorProps {
  initialXml?: string;
  onSave?: (config: { xml: string; js: string }) => void;
}

const BlocklyEditor: React.FC<BlocklyEditorProps> = ({ initialXml, onSave }) => {
  const [xml, setXml] = useState(initialXml || '');
  const [js, setJs] = useState('');

  const handleSave = () => {
    if (onSave) {
      onSave({ xml, js });
    }
  };

  return (
    <div className="flex flex-col h-[700px] border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm">
      <div className="flex items-center justify-between px-4 py-2 border-b border-gray-200 bg-gray-50">
        <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wider">Automation Editor</h3>
        <button
          onClick={handleSave}
          className="px-3 py-1 bg-blue-600 text-white text-xs font-bold rounded hover:bg-blue-700 transition-colors"
        >
          Save Workflow
        </button>
      </div>

      <div className="flex flex-1 overflow-hidden">
        {/* Blockly Pane */}
        <div className="flex-1 relative">
          <BlocklyComponent
            initialXml={initialXml}
            onXmlChange={setXml}
            onJsChange={setJs}
            className="absolute inset-0"
          />
        </div>

        {/* Code Preview Pane */}
        <div className="w-1/3 border-l border-gray-200 bg-gray-900 overflow-auto">
          <div className="p-3">
            <div className="text-[10px] font-bold text-gray-500 uppercase mb-2 tracking-widest">Generated JavaScript</div>
            <pre className="text-xs text-blue-300 font-mono whitespace-pre-wrap">
              {js || '// Start building blocks to see code...'}
            </pre>
          </div>
        </div>
      </div>
    </div>
  );
};

export default BlocklyEditor;
