{{-- A picture across the page, fading into the paper below it (after design-project.pdf: the shophouse render above Type). --}}
@props(['image'])

<figure class="pj-band" aria-hidden="true">
  <x-project.img :image="$image" alt="" />
</figure>
