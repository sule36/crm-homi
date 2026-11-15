export function splitCoordinatorsByRole(coordinators = []) {
    const devStaff = [];
    const salesAgents = [];
    for (const c of coordinators || []) {
        if (c.is_developer_staff || ['master_lead', 'inhouse', 'inhouse_developer', 'inhouse_master_lead'].includes(c.agent_type)) {
            devStaff.push(c);
        } else {
            salesAgents.push(c);
        }
    }
    return { devStaff, salesAgents };
}

export function resolveInitialSignerKey({ reservation = null, defaultDeveloperSigner = null } = {}) {
    if (reservation) {
        const slot = reservation.custom_overrides?.signer_slot;
        if (['finance', 'sig1', 'sig2'].includes(slot)) {
            return `preset:${slot}`;
        }
        if (reservation.agent_coordinator_id) {
            return `user:${reservation.agent_coordinator_id}`;
        }
        return 'custom';
    }

    if (defaultDeveloperSigner?.name) {
        return `preset:${defaultDeveloperSigner.slot || 'sig1'}`;
    }

    return '';
}

export function applySignerSelection(key, { form, developerSignatories = [], coordinators = [] }) {
    if (!key || key === 'custom') {
        form.signer_slot = 'custom';
        form.agent_coordinator_id = '';
        return { hasImage: false, image: null };
    }

    if (key.startsWith('preset:')) {
        const slot = key.replace('preset:', '');
        const preset = (developerSignatories || []).find((s) => s.key === slot);
        form.signer_slot = slot;
        form.agent_coordinator_id = '';
        if (preset) {
            form.agent_coordinator_name = preset.name || preset.title || '';
            form.agent_coordinator_title = preset.title || 'Developer Representative';
            return { hasImage: Boolean(preset.image), image: preset.image || null };
        }
        return { hasImage: false, image: null };
    }

    if (key.startsWith('user:')) {
        const userId = key.replace('user:', '');
        const coord = (coordinators || []).find((c) => String(c.id) === String(userId));
        form.signer_slot = 'user';
        form.agent_coordinator_id = coord ? coord.id : userId;
        if (coord) {
            form.agent_coordinator_name = coord.name || '';
            form.agent_coordinator_title = coord.signer_title
                || (coord.agent_type === 'master_lead' ? 'Master Lead / Agent Coordinator' : 'Sales Coordinator');
        }
        return { hasImage: false, image: null };
    }

    return { hasImage: false, image: null };
}

