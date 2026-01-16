<template>
    <NcContent app-name="contractmanager">
        <NcAppNavigation>
            <NcAppNavigationItem
                :name="t('contractmanager', 'Verträge')"
                icon="icon-files-dark"
                :class="{ active: currentView === 'contracts' }"
                @click="currentView = 'contracts'" />
            <NcAppNavigationItem
                :name="t('contractmanager', 'Archiv')"
                icon="icon-history"
                :class="{ active: currentView === 'archive' }"
                @click="currentView = 'archive'" />
            <template #footer>
                <NcAppNavigationItem
                    :name="t('contractmanager', 'Einstellungen')"
                    icon="icon-settings-dark"
                    :class="{ active: currentView === 'settings' }"
                    @click="currentView = 'settings'" />
            </template>
        </NcAppNavigation>

        <NcAppContent>
            <div class="contractmanager-content">
                <h2>{{ t('contractmanager', 'ContractManager') }}</h2>
                <p>{{ t('contractmanager', 'Willkommen bei ContractManager') }}</p>
            </div>
        </NcAppContent>
    </NcContent>
</template>

<script>
import {
    NcContent,
    NcAppNavigation,
    NcAppNavigationItem,
    NcAppContent,
} from '@nextcloud/vue'

export default {
    name: 'App',
    components: {
        NcContent,
        NcAppNavigation,
        NcAppNavigationItem,
        NcAppContent,
    },
    data() {
        return {
            currentView: 'contracts',
        }
    },
}
</script>

<style scoped>
.contractmanager-content {
    padding: 20px;
}
</style>
