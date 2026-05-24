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
};
