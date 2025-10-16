<template>
  <svg v-if="name === 'cheveron-down'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
    <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
  </svg>
  <svg v-else-if="name === 'dashboard'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
    <path d="M10 20a10 10 0 1 1 0-20 10 10 0 0 1 0 20zm-5.6-4.29a9.95 9.95 0 0 1 11.2 0 8 8 0 1 0-11.2 0zm6.12-7.64l3.02-3.02 1.41 1.41-3.02 3.02a2 2 0 1 1-1.41-1.41z" />
  </svg>
  <svg v-else-if="name === 'office'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
    <path d="M0 4c0-1.1.9-2 2-2h15a1 1 0 0 1 1 1v1H2v12h16v-1a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H2a2 2 0 0 1-2-2V4z" />
  </svg>
  <svg v-else-if="name === 'printer'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
    <path d="M4 16H0V6h20v10h-4v4H4v-4zm2-4v6h8v-6H6zM4 0h12v5H4V0zM2 8v2h2V8H2zm4 0v2h2V8H6z" />
  </svg>
</template>

<script>
export default {
  props: {
    name: String,
  },
}
</script>
