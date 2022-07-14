<textarea name="{{$name}}" class="w-100 form-control" @disabled($disabled)
onfocus="(() => {
        this.style.overflow = 'hidden';
        this.style.height = 0;
        this.style.height = this.scrollHeight + 'px';
    })()"
          onkeyup="(() => {
        this.style.overflow = 'hidden';
        this.style.height = 0;
        this.style.height = this.scrollHeight + 'px';
    })()">
{{$content}}
</textarea>
