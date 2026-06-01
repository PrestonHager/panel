import React from 'react';
import tw from 'twin.macro';
import Label from '@/components/elements/Label';
import { PluginPermissionsCatalog } from '@/api/plugins/getPluginPermissionsCatalog';

interface Props {
    catalog: PluginPermissionsCatalog;
    values: Record<string, string[]>;
    onChange: (pluginId: string, permission: string, checked: boolean) => void;
    editable: string[];
}

export default ({ catalog, values, onChange, editable }: Props) => {
    const pluginIds = Object.keys(catalog);

    if (pluginIds.length === 0) {
        return null;
    }

    return (
        <div css={tw`mt-6`}>
            <Label>Plugin Permissions</Label>
            {pluginIds.map((pluginId) => (
                <div key={pluginId} css={tw`mt-4 border border-neutral-700 rounded p-4`}>
                    <p css={tw`text-sm text-neutral-300 font-medium mb-2`}>{pluginId}</p>
                    <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-2`}>
                        {Object.entries(catalog[pluginId]).map(([permission, label]) => {
                            const key = `${pluginId}.${permission}`;
                            const checked = (values[pluginId] || []).includes(permission);
                            const disabled = !editable.includes(key) && !editable.includes('*');

                            return (
                                <label key={key} css={tw`flex items-center text-sm cursor-pointer`}>
                                    <input
                                        type={'checkbox'}
                                        css={tw`mr-2`}
                                        checked={checked}
                                        disabled={disabled}
                                        onChange={(e) => onChange(pluginId, permission, e.target.checked)}
                                    />
                                    <span>
                                        {label} <code css={tw`text-neutral-500 text-xs`}>({permission})</code>
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
};
