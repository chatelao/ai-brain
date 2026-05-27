'use client';

import React, { useEffect, useRef } from 'react';
import * as Blockly from 'blockly';
import { javascriptGenerator } from 'blockly/javascript';
import { defineCustomBlocks } from './custom_blocks';

// Initialize custom blocks
defineCustomBlocks();

interface BlocklyComponentProps {
  initialXml?: string;
  toolboxConfig?: Blockly.utils.toolbox.ToolboxDefinition;
  onXmlChange?: (xml: string) => void;
  onJsChange?: (js: string) => void;
  className?: string;
}

const BlocklyComponent: React.FC<BlocklyComponentProps> = ({
  initialXml,
  toolboxConfig,
  onXmlChange,
  onJsChange,
  className,
}) => {
  const blocklyDiv = useRef<HTMLDivElement>(null);
  const workspace = useRef<Blockly.WorkspaceSvg | null>(null);

  // Load XML when initialXml changes (e.g. after data fetch)
  useEffect(() => {
    if (workspace.current && initialXml) {
      workspace.current.clear();
      try {
        const dom = Blockly.utils.xml.textToDom(initialXml);
        Blockly.Xml.appendDomToWorkspace(dom, workspace.current);
      } catch (e) {
        console.error('Failed to load updated XML', e);
      }
    }
  }, [initialXml]);

  useEffect(() => {
    if (!blocklyDiv.current) return;

    workspace.current = Blockly.inject(blocklyDiv.current, {
      toolbox: toolboxConfig || {
        kind: 'categoryToolbox',
        contents: [
          {
            kind: 'category',
            name: 'Events',
            colour: '230',
            contents: [
              { kind: 'block', type: 'on_event' },
            ],
          },
          {
            kind: 'category',
            name: 'Actions',
            colour: '160',
            contents: [
              { kind: 'block', type: 'notify' },
              { kind: 'block', type: 'merge' },
              { kind: 'block', type: 'duplicate' },
              { kind: 'block', type: 'set_label' },
              { kind: 'block', type: 'remove_label' },
              { kind: 'block', type: 'rename_label' },
              { kind: 'block', type: 'post_comment' },
              { kind: 'block', type: 'trigger_agent' },
            ],
          },
          {
            kind: 'category',
            name: 'Conditions',
            colour: '210',
            contents: [
              { kind: 'block', type: 'read_label' },
              { kind: 'block', type: 'is_task_ready' },
              { kind: 'block', type: 'get_task_status' },
              { kind: 'block', type: 'get_task_title' },
              { kind: 'block', type: 'get_issue_number' },
              { kind: 'block', type: 'is_pr_draft' },
            ],
          },
          {
            kind: 'category',
            name: 'Logic',
            colour: '%{BKY_LOGIC_HUE}',
            contents: [
              { kind: 'block', type: 'controls_if' },
              { kind: 'block', type: 'logic_compare' },
              { kind: 'block', type: 'logic_operation' },
              { kind: 'block', type: 'logic_negate' },
              { kind: 'block', type: 'logic_boolean' },
            ],
          },
          {
            kind: 'category',
            name: 'Loops',
            colour: '%{BKY_LOOPS_HUE}',
            contents: [
              { kind: 'block', type: 'controls_repeat_ext' },
            ],
          },
          {
            kind: 'category',
            name: 'Math',
            colour: '%{BKY_MATH_HUE}',
            contents: [
              { kind: 'block', type: 'math_number' },
              { kind: 'block', type: 'math_arithmetic' },
              { kind: 'block', type: 'math_single' },
            ],
          },
          {
            kind: 'category',
            name: 'Text',
            colour: '%{BKY_TEXTS_HUE}',
            contents: [
              { kind: 'block', type: 'text' },
              { kind: 'block', type: 'text_join' },
              { kind: 'block', type: 'text_append' },
            ],
          },
          {
            kind: 'category',
            name: 'Variables',
            colour: '%{BKY_VARIABLES_HUE}',
            custom: 'VARIABLE',
          },
        ],
      },
    });

    if (initialXml) {
      try {
        const dom = Blockly.utils.xml.textToDom(initialXml);
        Blockly.Xml.appendDomToWorkspace(dom, workspace.current);
      } catch (e) {
        console.error('Failed to load initial XML', e);
      }
    }

    const handleChange = () => {
      if (!workspace.current) return;

      if (onXmlChange) {
        const xmlDom = Blockly.Xml.workspaceToDom(workspace.current);
        const xmlText = Blockly.Xml.domToText(xmlDom);
        onXmlChange(xmlText);
      }

      if (onJsChange) {
        const code = javascriptGenerator.workspaceToCode(workspace.current);
        onJsChange(code);
      }
    };

    workspace.current.addChangeListener(handleChange);

    // Initial code generation
    handleChange();

    const handleResize = () => {
      if (workspace.current) {
        Blockly.svgResize(workspace.current);
      }
    };

    window.addEventListener('resize', handleResize);

    return () => {
      window.removeEventListener('resize', handleResize);
      if (workspace.current) {
        workspace.current.dispose();
      }
    };
  }, []);

  return <div ref={blocklyDiv} className={className} style={{ height: '100%', width: '100%' }} />;
};

export default BlocklyComponent;
