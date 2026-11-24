<script setup lang="ts">
import KtButton from '@/Components/UI/KtButton.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

type Location = { name: Record<string, string>; address: Record<string, string>; coordinates: string };

/** Store locations shown in the footer and on the contact page, each with its map pin. */
const locations = defineModel<Location[]>({ required: true });
const props = defineProps<{ locales: string[]; errors: Record<string, string> }>();
const { t } = useTrans();

const blank = () => Object.fromEntries(props.locales.map((l) => [l, '']));
const add = () => locations.value.push({ name: blank(), address: blank(), coordinates: '' });
const remove = (index: number) => locations.value.splice(index, 1);
const move = (index: number, by: number) => {
    const [location] = locations.value.splice(index, 1);
    locations.value.splice(index + by, 0, location);
};
/** Opens the pin (or the address) in Google Maps so the editor can check it. */
const preview = (location: Location) =>
    `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(location.coordinates.trim() || location.address.ar || '')}`;
const dirFor = (l: string) => (l === 'en' ? 'ltr' : 'rtl');
const error = (index: number, field: string) => props.errors[`locations.${index}.${field}`];
</script>

<template>
    <fieldset class="grid gap-3">
        <legend class="mb-1 text-sm font-semibold">{{ t('admin.locations') }}</legend>
        <p class="text-xs text-muted-foreground">{{ t('admin.locations_help') }}</p>

        <div v-for="(location, index) in locations" :key="index" class="grid gap-3 rounded-lg border border-border p-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-semibold">{{ location.name.ar || t('admin.location') }} #{{ index + 1 }}</p>
                <div class="flex gap-1">
                    <KtButton variant="ghost" size="sm" icon="arrow-up" icon-only :disabled="index === 0" :aria-label="t('admin.move_up')" @click="move(index, -1)" />
                    <KtButton variant="ghost" size="sm" icon="arrow-down" icon-only :disabled="index === locations.length - 1" :aria-label="t('admin.move_down')" @click="move(index, 1)" />
                    <KtButton variant="ghost" size="sm" icon="trash" icon-only :aria-label="t('common.delete')" @click="remove(index)" />
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div v-for="l in locales" :key="`n${l}`">
                    <label :for="`ln-${index}-${l}`" class="mb-1 block text-xs font-medium">{{ t('admin.location_name') }} ({{ l }})<span v-if="l === 'ar'" class="text-destructive"> *</span></label>
                    <input :id="`ln-${index}-${l}`" v-model="location.name[l]" class="kt-input" :dir="dirFor(l)" />
                    <p v-if="error(index, `name.${l}`)" class="mt-1 text-xs text-destructive">{{ error(index, `name.${l}`) }}</p>
                </div>
                <div v-for="l in locales" :key="`a${l}`">
                    <label :for="`la-${index}-${l}`" class="mb-1 block text-xs font-medium">{{ t('admin.address') }} ({{ l }})<span v-if="l === 'ar'" class="text-destructive"> *</span></label>
                    <textarea :id="`la-${index}-${l}`" v-model="location.address[l]" class="kt-textarea" rows="2" :dir="dirFor(l)" />
                    <p v-if="error(index, `address.${l}`)" class="mt-1 text-xs text-destructive">{{ error(index, `address.${l}`) }}</p>
                </div>
            </div>

            <div class="grid items-end gap-3 md:grid-cols-[1fr_auto]">
                <div>
                    <label :for="`lc-${index}`" class="mb-1 block text-xs font-medium">{{ t('admin.coordinates') }}</label>
                    <input :id="`lc-${index}`" v-model="location.coordinates" class="kt-input" dir="ltr" placeholder="31.507, 35.0374" />
                    <p class="mt-1 text-xs text-muted-foreground">{{ t('admin.coordinates_help') }}</p>
                    <p v-if="error(index, 'coordinates')" class="mt-1 text-xs text-destructive">{{ error(index, 'coordinates') }}</p>
                </div>
                <a :href="preview(location)" target="_blank" rel="noopener" class="kt-btn kt-btn-ghost kt-btn-sm">
                    <KtIcon name="geolocation" />{{ t('admin.check_on_map') }}
                </a>
            </div>
        </div>

        <div><KtButton variant="outline" size="sm" icon="plus" @click="add">{{ t('admin.add_location') }}</KtButton></div>
    </fieldset>
</template>
