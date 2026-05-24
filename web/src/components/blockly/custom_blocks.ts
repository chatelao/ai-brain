import * as Blockly from 'blockly';
import { javascriptGenerator } from 'blockly/javascript';

export const defineCustomBlocks = () => {
  // on_event trigger block
  Blockly.Blocks['on_event'] = {
    init: function() {
      this.appendDummyInput()
          .appendField("On Event")
          .appendField(new Blockly.FieldDropdown([
            ["Issue Labeled", "ISSUE_LABELED"],
            ["Issue Closed", "ISSUE_CLOSED"],
            ["PR Created", "PR_CREATED"],
            ["PR Merged", "PR_MERGED"],
            ["Checks Completed", "CHECKS_COMPLETED"],
            ["Agent Error", "AGENT_ERROR"]
          ]), "EVENT");
      this.appendStatementInput("STACK")
          .setCheck(null)
          .appendField("do");
      this.setColour(230);
      this.setTooltip("Trigger logic when a specific system or GitHub event occurs.");
      this.setHelpUrl("");
    }
  };

  javascriptGenerator.forBlock['on_event'] = (block: Blockly.Block, generator: any) => {
    const dropdown_event = block.getFieldValue('EVENT');
    const statements_stack = generator.statementToCode(block, 'STACK');
    return `onEvent("${dropdown_event}", (event) => {\n${statements_stack}});\n`;
  };

  // notify action block
  Blockly.Blocks['notify'] = {
    init: function() {
      this.appendDummyInput()
          .appendField("Notify")
          .appendField(new Blockly.FieldTextInput("Message..."), "MESSAGE");
      this.setPreviousStatement(true, null);
      this.setNextStatement(true, null);
      this.setColour(160);
      this.setTooltip("Send a notification via Telegram and In-App Inbox.");
      this.setHelpUrl("");
    }
  };

  javascriptGenerator.forBlock['notify'] = (block: Blockly.Block) => {
    const text_message = block.getFieldValue('MESSAGE');
    return `notify("${text_message}");\n`;
  };

  // merge action block
  Blockly.Blocks['merge'] = {
    init: function() {
      this.appendDummyInput()
          .appendField("Merge Pull Request");
      this.setPreviousStatement(true, null);
      this.setNextStatement(true, null);
      this.setColour(160);
      this.setTooltip("Merge the Pull Request associated with the current task.");
      this.setHelpUrl("");
    }
  };

  javascriptGenerator.forBlock['merge'] = () => {
    return `merge();\n`;
  };

  // duplicate action block
  Blockly.Blocks['duplicate'] = {
    init: function() {
      this.appendDummyInput()
          .appendField("Duplicate Issue");
      this.setPreviousStatement(true, null);
      this.setNextStatement(true, null);
      this.setColour(160);
      this.setTooltip("Create a copy of the current issue (useful for auto-repeat).");
      this.setHelpUrl("");
    }
  };

  javascriptGenerator.forBlock['duplicate'] = () => {
    return `duplicate();\n`;
  };

  // set_label action block
  Blockly.Blocks['set_label'] = {
    init: function() {
      this.appendDummyInput()
          .appendField("Set Label")
          .appendField(new Blockly.FieldTextInput("label-name"), "LABEL");
      this.setPreviousStatement(true, null);
      this.setNextStatement(true, null);
      this.setColour(160);
      this.setTooltip("Add a specific label to the GitHub issue.");
      this.setHelpUrl("");
    }
  };

  javascriptGenerator.forBlock['set_label'] = (block: Blockly.Block) => {
    const text_label = block.getFieldValue('LABEL');
    return `setLabel("${text_label}");\n`;
  };

  // remove_label action block
  Blockly.Blocks['remove_label'] = {
    init: function() {
      this.appendDummyInput()
          .appendField("Remove Label")
          .appendField(new Blockly.FieldTextInput("label-name"), "LABEL");
      this.setPreviousStatement(true, null);
      this.setNextStatement(true, null);
      this.setColour(160);
      this.setTooltip("Remove a specific label from the GitHub issue.");
      this.setHelpUrl("");
    }
  };

  javascriptGenerator.forBlock['remove_label'] = (block: Blockly.Block) => {
    const text_label = block.getFieldValue('LABEL');
    return `removeLabel("${text_label}");\n`;
  };

  // post_comment action block
  Blockly.Blocks['post_comment'] = {
    init: function() {
      this.appendDummyInput()
          .appendField("Post Comment")
          .appendField(new Blockly.FieldTextInput("Comment body..."), "COMMENT");
      this.setPreviousStatement(true, null);
      this.setNextStatement(true, null);
      this.setColour(160);
      this.setTooltip("Add a comment to the GitHub issue or Pull Request.");
      this.setHelpUrl("");
    }
  };

  javascriptGenerator.forBlock['post_comment'] = (block: Blockly.Block) => {
    const text_comment = block.getFieldValue('COMMENT');
    return `postComment("${text_comment}");\n`;
  };

  // trigger_agent action block
  Blockly.Blocks['trigger_agent'] = {
    init: function() {
      this.appendDummyInput()
          .appendField("Trigger Agent");
      this.setPreviousStatement(true, null);
      this.setNextStatement(true, null);
      this.setColour(160);
      this.setTooltip("Manually start or retry a Jules session.");
      this.setHelpUrl("");
    }
  };

  javascriptGenerator.forBlock['trigger_agent'] = () => {
    return `triggerAgent();\n`;
  };

  // read_label predicate block
  Blockly.Blocks['read_label'] = {
    init: function() {
      this.appendDummyInput()
          .appendField("Has Label")
          .appendField(new Blockly.FieldTextInput("label-name"), "LABEL");
      this.setOutput(true, "Boolean");
      this.setColour(210);
      this.setTooltip("Checks if a specific label exists on the GitHub issue.");
      this.setHelpUrl("");
    }
  };

  javascriptGenerator.forBlock['read_label'] = (block: Blockly.Block) => {
    const text_label = block.getFieldValue('LABEL');
    return [`readLabel("${text_label}")`, (javascriptGenerator as any).ORDER_FUNCTION_CALL];
  };

  // is_task_ready predicate block
  Blockly.Blocks['is_task_ready'] = {
    init: function() {
      this.appendDummyInput()
          .appendField("Is Task Ready");
      this.setOutput(true, "Boolean");
      this.setColour(210);
      this.setTooltip("Checks if the current task is in READY status.");
      this.setHelpUrl("");
    }
  };

  javascriptGenerator.forBlock['is_task_ready'] = () => {
    return [`isTaskReady()`, (javascriptGenerator as any).ORDER_FUNCTION_CALL];
  };
};
