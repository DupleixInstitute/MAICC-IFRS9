<template>
    <button type="button" @click="showNotification">
        <slot/>
        <teleport to="#dropdown">
            <div>
                <div
                    style="position: fixed; top: 0; right: 0; left: 0; bottom: 0; z-index: 99998; background: black; opacity: .2"/>
                <div id="dropdown" style="position: absolute; z-index: 99999;">
                    <slot name="dropdown"/>
                </div>
            </div>
        </teleport>
    </button>
</template>

<script>


export default {
    setup() {
        const isOpen = ref(false)

        var closePopup

        const showNotification = () => {
            isOpen.value = true

            clearTimeout(closePopup)

            closePopup = setTimeout(() => {
                isOpen.value = false
            }, 2000)
        }

        return {
            isOpen,
            showNotification
        }
    }
}
</script>
