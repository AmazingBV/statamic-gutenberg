import StatamicGutenberg from './components/fieldtypes/StatamicGutenberg.vue'

if (window.Statamic?.booting) {
    Statamic.booting(() => {
        Statamic.component('gutenberg-fieldtype', StatamicGutenberg)
    })
}
