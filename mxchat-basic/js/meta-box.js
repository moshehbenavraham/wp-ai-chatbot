(function() {
    if (typeof wp !== 'undefined' && wp.editPost && wp.components && wp.element) {
        var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
        var CheckboxControl = wp.components.CheckboxControl;
        var SelectControl = wp.components.SelectControl;
        var PanelRow = wp.components.PanelRow;
        var Notice = wp.components.Notice;
        var useSelect = wp.data.useSelect;
        var useDispatch = wp.data.useDispatch;
        
        function MxChatSettingsPanel() {
            var meta = useSelect(function(select) {
                return select('core/editor').getEditedPostAttribute('meta') || {};
            });
            
            var editPost = useDispatch('core/editor').editPost;
            var hideChatbot = meta._mxchat_hide_chatbot === '1';
            var selectedBot = meta._mxchat_selected_bot || '';
            
            // Bot options will be populated from localized data
            var botOptions = [
                { label: mxchatMetaBox.strings.useGlobalSetting, value: '' }
            ];
            
            if (mxchatMetaBox.hasMultibot && mxchatMetaBox.availableBots) {
                Object.keys(mxchatMetaBox.availableBots).forEach(function(botId) {
                    botOptions.push({ 
                        label: mxchatMetaBox.availableBots[botId], 
                        value: botId 
                    });
                });
            }
            
            var elements = [
                wp.element.createElement(
                    PanelRow,
                    null,
                    wp.element.createElement(CheckboxControl, {
                        label: mxchatMetaBox.strings.hideChatbot,
                        help: mxchatMetaBox.strings.hideChatbotHelp,
                        checked: hideChatbot,
                        onChange: function(value) {
                            editPost({
                                meta: Object.assign({}, meta, {
                                    _mxchat_hide_chatbot: value ? '1' : ''
                                })
                            });
                        }
                    })
                )
            ];
            
            // Add bot selection if multi-bot is available
            if (mxchatMetaBox.hasMultibot && botOptions.length > 1) {
                elements.push(
                    wp.element.createElement(
                        PanelRow,
                        null,
                        wp.element.createElement(SelectControl, {
                            label: mxchatMetaBox.strings.selectBot,
                            value: selectedBot,
                            options: botOptions,
                            onChange: function(value) {
                                editPost({
                                    meta: Object.assign({}, meta, {
                                        _mxchat_selected_bot: value
                                    })
                                });
                            }
                        })
                    )
                );
                
                // Add info notice
                elements.push(
                    wp.element.createElement(
                        Notice,
                        {
                            status: 'info',
                            isDismissible: false,
                            className: 'mxchat-info-notice'
                        },
                        mxchatMetaBox.globalAutoshow 
                            ? mxchatMetaBox.strings.globalAutoshowOn
                            : mxchatMetaBox.strings.globalAutoshowOff
                    )
                );
            }
            
            return wp.element.createElement(
                PluginDocumentSettingPanel,
                {
                    name: 'mxchat-settings',
                    title: mxchatMetaBox.strings.panelTitle,
                    className: 'mxchat-settings-panel'
                },
                elements
            );
        }
        
        wp.plugins.registerPlugin('mxchat-settings', {
            render: MxChatSettingsPanel
        });
    }
})();