import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const MySidebar = () => (
    <>
        <PluginSidebarMoreMenuItem target="my-sidebar">
            {__('My Sidebar', 'text-domain')}
        </PluginSidebarMoreMenuItem>
        <PluginSidebar
            name="my-sidebar"
            title={__('My Sidebar', 'text-domain')}
            icon="admin-generic"
        >
            <PanelBody title={__('Custom Sidebar Panel', 'text-domain')}>
                <TextControl
                    label={__('Custom Field', 'text-domain')}
                    value={yourValue}
                    onChange={setYourValue}
                />
            </PanelBody>
        </PluginSidebar>
    </>
);

registerPlugin('my-sidebar', {
    render: MySidebar,
});