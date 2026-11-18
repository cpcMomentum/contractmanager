<!--
	SPDX-FileCopyrightText: 2026 cpcMomentum
	SPDX-License-Identifier: AGPL-3.0-or-later

	Link an jeder Grenze des freien Umfangs (app-links-v1).
-->
<template>
	<a v-if="href"
		class="werkplus-unlock"
		:href="href"
		target="_blank"
		rel="noopener noreferrer">
		{{ t('contractmanager', 'Mit WerkPlus freischalten') }}
	</a>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { werkplusLinks } from '../werkplusLinks'

const href = werkplusLinks().buy
</script>

<style scoped>
.werkplus-unlock {
	display: inline-block;
	margin-top: 8px;
	font-weight: 600;
}
</style>
